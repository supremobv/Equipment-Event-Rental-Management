<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Maintenance;
use App\Models\Setting;
use App\Models\User;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Equipment $item;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'active' => true, 'must_change_password' => false]);
        $this->actingAs($this->admin);
        Setting::create(['business_name' => 'Test Rentals', 'tax_bps' => 1200]);
        $cat = Category::create(['name' => 'Chairs']);
        $this->item = Equipment::create(['category_id' => $cat->id, 'name' => 'Oak chair', 'sku' => 'CHAIR', 'quantity' => 10, 'daily_rate' => 10000, 'replacement_cost' => 100000]);
        $this->customer = Customer::create(['name' => 'Test Customer', 'phone' => '12345']);
    }

    private function quote(array $extra = []): Booking
    {
        return app(RentalService::class)->saveQuote(array_replace(['customer_id' => $this->customer->id, 'event_name' => 'Test event', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDay()->toDateString(), 'fulfillment' => 'pickup', 'discount' => '10.00', 'delivery_fee' => '20.00', 'deposit_required' => '100.00', 'items' => [['equipment_id' => $this->item->id, 'quantity' => 6]]], $extra));
    }

    private function payment(string $kind, string $amount): array
    {
        return ['request_key' => (string) Str::uuid(), 'kind' => $kind, 'amount' => $amount, 'method' => 'cash', 'paid_on' => today()->toDateString()];
    }

    public function test_full_quote_payment_handover_return_and_deposit_refund_cycle(): void
    {
        $s = app(RentalService::class);
        $b = $this->quote();
        $this->assertSame(120000, $b->subtotal);
        $this->assertSame(135520, $b->total);
        $s->transition($b, 'confirm');
        $s->payment($b, $this->payment('deposit', '100.00'));
        $s->payment($b, $this->payment('rental', '1355.20'));
        $s->transition($b, 'checkout');
        $this->assertSame('out', $b->fresh()->status);
        $s->transition($b, 'return', ['return_fee' => '50.00', 'return_notes' => 'All units inspected']);
        $s->payment($b, $this->payment('rental', '50.00'));
        $s->payment($b, $this->payment('deposit_refund', '100.00'));
        $b = $b->fresh('payments');
        $this->assertSame('returned', $b->status);
        $this->assertSame(0, $b->balance());
        $this->assertSame(0, $b->depositHeld());
        $this->assertSame(10, $s->available($this->item, today()->toDateString(), today()->addDay()->toDateString()));
    }

    public function test_conflicting_confirmation_is_rejected_and_quotes_do_not_hold_stock(): void
    {
        $a = $this->quote();
        $b = $this->quote();
        $s = app(RentalService::class);
        $this->assertSame(10, $s->available($this->item, today()->toDateString(), today()->toDateString()));
        $s->transition($a, 'confirm');
        $this->post(route('bookings.action', [$b, 'confirm']))->assertSessionHasErrors('booking');
        $this->assertSame('quote', $b->fresh()->status);
    }

    public function test_disjoint_reservations_use_peak_quantity_not_sum(): void
    {
        $s = app(RentalService::class);
        foreach ([2, 5] as $offset) {
            $b = $this->quote(['starts_on' => today()->addDays($offset)->toDateString(), 'ends_on' => today()->addDays($offset)->toDateString()]);
            $s->transition($b, 'confirm');
        }$this->assertSame(4, $s->available($this->item, today()->toDateString(), today()->addDays(8)->toDateString()));
    }

    public function test_same_day_boundaries_overlap(): void
    {
        $s = app(RentalService::class);
        $a = $this->quote();
        $s->transition($a, 'confirm');
        $b = $this->quote(['starts_on' => today()->addDay()->toDateString(), 'ends_on' => today()->addDays(2)->toDateString()]);
        $this->post(route('bookings.action', [$b, 'confirm']))->assertSessionHasErrors();
    }

    public function test_checkout_requires_deposit_and_return_cannot_repeat(): void
    {
        $b = $this->quote();
        app(RentalService::class)->transition($b, 'confirm');
        $this->post(route('bookings.action', [$b, 'checkout']))->assertSessionHasErrors('booking');
        $this->assertSame('confirmed', $b->fresh()->status);
        $this->post(route('bookings.action', [$b, 'return']), ['return_fee' => '0'])->assertSessionHasErrors('booking');
    }

    public function test_payment_limits_refunds_and_idempotency(): void
    {
        $b = $this->quote();
        $s = app(RentalService::class);
        $s->transition($b, 'confirm');
        $p = $this->payment('rental', '20.00');
        $this->post(route('bookings.pay', $b), $p)->assertSessionHasNoErrors();
        $this->post(route('bookings.pay', $b), $p)->assertSessionHasNoErrors();
        $this->assertSame(1, $b->payments()->count());
        $this->post(route('bookings.pay', $b), $this->payment('rental', '99999.00'))->assertSessionHasErrors('booking');
        $this->post(route('bookings.pay', $b), $this->payment('refund', '21.00'))->assertSessionHasErrors('booking');
        $this->post(route('bookings.action', [$b, 'cancel']))->assertSessionHasNoErrors();
        $this->post(route('bookings.pay', $b), $this->payment('refund', '20.00'))->assertSessionHasNoErrors();
        $this->assertSame(0, $b->fresh('payments')->netPaid());
    }

    public function test_maintenance_blocks_stock_and_completion_releases_it(): void
    {
        $m = Maintenance::create(['equipment_id' => $this->item->id, 'quantity' => 5, 'reason' => 'Repair']);
        $b = $this->quote();
        $this->post(route('bookings.action', [$b, 'confirm']))->assertSessionHasErrors('booking');
        $this->post(route('maintenance.complete', $m))->assertSessionHasNoErrors();
        $this->post(route('bookings.action', [$b, 'confirm']))->assertSessionHasNoErrors();
    }

    public function test_inventory_reduction_cannot_break_reservations(): void
    {
        $b = $this->quote();
        app(RentalService::class)->transition($b, 'confirm');
        $this->put(route('equipment.update', $this->item), ['name' => 'Oak chair', 'sku' => 'CHAIR', 'category_id' => $this->item->category_id, 'quantity' => 1, 'daily_rate' => '100', 'replacement_cost' => '1000', 'active' => 1])->assertSessionHasErrors('quantity');
        $this->assertSame(10, $this->item->fresh()->quantity);
    }

    public function test_viewer_cannot_mutate_or_access_admin_finance_reports(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'active' => true, 'must_change_password' => false]);
        $this->actingAs($viewer);
        $b = $this->quote();
        $this->post(route('bookings.action', [$b, 'confirm']))->assertForbidden();
        $this->post(route('bookings.pay', $b), $this->payment('rental', '10'))->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->get('/inventory')->assertOk();
    }

    public function test_operations_cannot_confirm_or_pay_and_finance_cannot_return(): void
    {
        $b = $this->quote();
        foreach (['operations', 'finance'] as $role) {
            $u = User::factory()->create(['role' => $role, 'active' => true, 'must_change_password' => false]);
            $this->actingAs($u);
            $this->post(route('bookings.action', [$b, 'confirm']))->assertForbidden();
            if ($role === 'operations') {
                $this->post(route('bookings.pay', $b), $this->payment('rental', '10'))->assertForbidden();
            } else {
                $this->post(route('bookings.action', [$b, 'return']))->assertForbidden();
            }
        }
    }

    public function test_inactive_and_password_change_required_accounts_are_restricted(): void
    {
        $this->admin->update(['must_change_password' => true]);
        $this->get('/')->assertRedirect(route('password.edit'));
        $this->admin->update(['active' => false]);
        $this->get('/')->assertRedirect('/login');
    }

    public function test_last_admin_cannot_be_disabled(): void
    {
        $this->put(route('users.update', $this->admin), ['name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'viewer', 'active' => 0])->assertSessionHasErrors('role');
        $this->assertTrue($this->admin->fresh()->active);
    }

    public function test_all_authenticated_screens_render(): void
    {
        $b = $this->quote();
        foreach (['/', '/bookings', '/bookings/create', '/bookings/'.$b->id, '/bookings/'.$b->id.'/edit', '/bookings/'.$b->id.'/print', '/calendar', '/inventory', '/inventory/create', '/inventory/'.$this->item->id.'/edit', '/availability', '/customers', '/customers/create', '/customers/'.$this->customer->id.'/edit', '/maintenance', '/reports', '/settings', '/users', '/users/create', '/users/'.$this->admin->id.'/edit', '/audit', '/password'] as $url) {
            $this->get($url)->assertOk();
        }$this->getJson('/api/v1/availability')->assertOk()->assertJsonPath('data.0.available', 10);
    }

    public function test_login_throttling_and_security_headers(): void
    {
        $this->post('/logout');
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', ['email' => 'missing@example.com', 'password' => 'invalid']);
        }$response->assertSessionHasErrors('email');
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_duplicate_equipment_rows_are_rejected(): void
    {
        $this->post('/bookings', ['customer_id' => $this->customer->id, 'event_name' => 'Test', 'starts_on' => today()->toDateString(), 'ends_on' => today()->toDateString(), 'fulfillment' => 'pickup', 'discount' => '0', 'delivery_fee' => '0', 'deposit_required' => '0', 'items' => [['equipment_id' => $this->item->id, 'quantity' => 1], ['equipment_id' => $this->item->id, 'quantity' => 1]]])->assertSessionHasErrors('items.0.equipment_id');
    }

    public function test_quotes_escape_customer_supplied_html(): void
    {
        $b = $this->quote(['event_name' => '<script>alert(1)</script>']);
        $this->get(route('bookings.show', $b))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_branding_and_raster_logo_can_change_but_currency_is_locked_after_bookings(): void
    {
        Storage::fake('public');
        $data = ['business_name' => 'My Event Business', 'tagline' => 'Your event partner', 'currency' => 'PHP', 'tax_percent' => '8.25', 'logo' => UploadedFile::fake()->image('logo.png', 200, 200)];
        $this->put('/settings', $data)->assertSessionHasNoErrors();
        $setting = Setting::firstOrFail();
        $this->assertSame('My Event Business', $setting->business_name);
        $this->assertSame(825, $setting->tax_bps);
        Storage::disk('public')->assertExists($setting->logo_path);
        $b = $this->quote();
        $this->put('/settings', array_replace($data, ['currency' => 'USD', 'logo' => null]))->assertSessionHasErrors('currency');
        $this->assertSame('PHP', Setting::firstOrFail()->currency);
        $this->assertSame(825, $b->tax_bps);
        $this->put('/settings', ['business_name' => 'My Event Business', 'currency' => 'PHP', 'tax_percent' => '0', 'tagline' => ''])->assertSessionHasNoErrors();
        $this->assertSame('', Setting::firstOrFail()->tagline);
        $this->assertSame(825, $b->fresh()->tax_bps);
    }

    public function test_executable_and_svg_uploads_are_rejected(): void
    {
        foreach (['attack.php', 'logo.svg'] as $filename) {
            $file = UploadedFile::fake()->createWithContent($filename, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
            $this->put('/settings', ['business_name' => 'Test Rentals', 'currency' => 'PHP', 'tax_percent' => '0', 'logo' => $file])->assertSessionHasErrors('logo');
        }
    }

    public function test_early_checkout_and_duplicate_return_are_rejected(): void
    {
        $s = app(RentalService::class);
        $b = $this->quote(['starts_on' => today()->addDays(2)->toDateString(), 'ends_on' => today()->addDays(3)->toDateString(), 'deposit_required' => '0']);
        $s->transition($b, 'confirm');
        $this->post(route('bookings.action', [$b, 'checkout']))->assertSessionHasErrors('booking');
        $b->update(['status' => 'out']);
        $this->post(route('bookings.action', [$b, 'return']), ['return_fee' => '50'])->assertSessionHasNoErrors();
        $total = $b->fresh()->total;
        $this->post(route('bookings.action', [$b, 'return']), ['return_fee' => '50'])->assertSessionHasErrors('booking');
        $this->assertSame($total, $b->fresh()->total);
    }

    public function test_report_balances_and_csv_are_consistent(): void
    {
        $b = $this->quote();
        $s = app(RentalService::class);
        $s->transition($b, 'confirm');
        $s->payment($b, $this->payment('rental', '100.00'));
        $this->get('/reports')->assertOk()->assertViewHas('outstanding', 125520)->assertViewHas('rental', 10000);
        $this->customer->update(['name' => '=HYPERLINK("example.com")']);
        $response = $this->get('/reports/export?from='.today()->toDateString().'&to='.today()->toDateString());
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('100.00', $csv);
    }
}
