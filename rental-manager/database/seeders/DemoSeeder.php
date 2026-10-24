<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        if (User::exists() || Booking::exists() || Equipment::exists() || Customer::exists()) {
            $this->command?->warn('Demo seed requires an empty business database. Existing data was preserved.');

            return;
        }
        $password = 'Gather!'.bin2hex(random_bytes(8));
        DB::transaction(function () use ($password) {
            $user = User::create(['name' => 'Alex Morgan', 'email' => 'admin@gather.test', 'password' => $password, 'role' => 'admin', 'active' => true, 'must_change_password' => true]);
            $catalog = [['Cross-back oak chair', 'CHR-001', 1, 180, 18000, 350000], ['Round banquet table', 'TBL-001', 1, 35, 45000, 850000], ['White event marquee · 3×6m', 'TNT-001', 2, 8, 250000, 3500000], ['Portable PA speaker', 'AUD-001', 3, 12, 150000, 2500000], ['Wireless microphone set', 'AUD-002', 3, 10, 65000, 1250000], ['Warm festoon lights · 20m', 'LGT-001', 4, 30, 45000, 450000], ['Linen tablecloth · ivory', 'DEC-001', 5, 70, 15000, 160000], ['Ceramic dinnerware set', 'DEC-002', 5, 200, 8500, 75000], ['Modular stage platform', 'STG-001', 2, 18, 110000, 2200000]];
            foreach ($catalog as [$name,$sku,$cat,$qty,$rate,$cost]) {
                Equipment::create(['name' => $name, 'sku' => $sku, 'category_id' => $cat, 'quantity' => $qty, 'daily_rate' => $rate, 'replacement_cost' => $cost, 'location' => 'Main warehouse · Bay '.str_pad((string) $cat, 2, '0', STR_PAD_LEFT), 'description' => 'Sample equipment. Customize the details for your business.']);
            }
            foreach ([['Sofia Reyes', 'Reyes & Co.', 'sofia@example.com'], ['James Tan', 'Bloom Events', 'james@example.com'], ['Isabella Cruz', 'Northstar Collective', 'isabella@example.com'], ['Daniel Lim', 'Lim Family', 'daniel@example.com'], ['Olivia Santos', 'Santos Studio', 'olivia@example.com']] as [$name,$company,$email]) {
                Customer::create(['name' => $name, 'company' => $company, 'email' => $email, 'phone' => '+63 917 555 01'.str_pad((string) Customer::count(), 2, '0', STR_PAD_LEFT), 'address' => 'Quezon City, Philippines']);
            }
            $events = [['Sofia & James · Garden wedding', 1, 3, 4, 'confirmed', [[1, 60], [2, 8], [6, 6]]], ['Bloom · Product launch', 2, 0, 1, 'out', [[4, 2], [5, 2], [9, 4]]], ['Northstar · Team celebration', 3, 6, 6, 'confirmed', [[1, 40], [2, 5], [3, 2]]], ['Lim family · Birthday dinner', 4, 9, 10, 'confirmed', [[7, 12], [8, 50]]], ['Santos Studio · Open house', 5, 12, 12, 'quote', [[4, 2], [6, 4]]], ['Autumn community gathering', 3, -8, -7, 'returned', [[1, 40], [6, 5]]]];
            foreach ($events as $idx => [$name,$customer,$start,$end,$status,$items]) {
                $days = $end - $start + 1;
                $subtotal = 0;
                $lines = [];
                foreach ($items as [$id,$qty]) {
                    $e = Equipment::findOrFail($id);
                    $value = $e->daily_rate * $qty * $days;
                    $subtotal += $value;
                    $lines[] = ['equipment_id' => $id, 'name' => $e->name, 'quantity' => $qty, 'daily_rate' => $e->daily_rate, 'days' => $days, 'total' => $value];
                }
                $b = Booking::create(['number' => 'GR-'.today()->format('ym').'-'.str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT), 'customer_id' => $customer, 'created_by' => $user->id, 'event_name' => $name, 'venue' => ['The Glass Garden', 'Bloom Creative Space', 'Northstar HQ', 'Private residence', 'Santos Studio', 'Community hall'][$idx], 'fulfillment' => $idx % 2 ? 'pickup' : 'delivery', 'starts_on' => today()->addDays($start), 'ends_on' => today()->addDays($end), 'status' => $status, 'subtotal' => $subtotal, 'total' => $subtotal + 150000, 'delivery_fee' => 150000, 'deposit_required' => 500000, 'checked_out_at' => in_array($status, ['out', 'returned']) ? now()->addDays($start) : null, 'returned_at' => $status === 'returned' ? now()->addDays($end) : null]);
                $b->items()->createMany($lines);
                if ($status !== 'quote') {
                    foreach ([['rental', $status === 'returned' ? $b->total : intdiv($b->total, 2)], ['deposit', 500000]] as [$kind,$amount]) {
                        $b->payments()->create(['user_id' => $user->id, 'kind' => $kind, 'amount' => $amount, 'method' => 'bank', 'reference' => 'DEMO-'.($idx + 1).'-'.$kind, 'paid_on' => today()->subDays(2)]);
                    }
                    if ($status === 'returned') {
                        $b->payments()->create(['user_id' => $user->id, 'kind' => 'deposit_refund', 'amount' => 500000, 'method' => 'bank', 'paid_on' => today()->subDays(1)]);
                    }
                }
            }
            Maintenance::create(['equipment_id' => 4, 'quantity' => 1, 'reason' => 'Routine sound check', 'due_on' => today()->addDays(2), 'notes' => 'Demo maintenance record.']);
            for ($i = 1; $i <= 5; $i++) {
                $b = Booking::create(['number' => 'GR-HISTORY-'.$i, 'customer_id' => 1, 'created_by' => $user->id, 'event_name' => 'Past event · '.today()->subMonths($i)->format('F'), 'starts_on' => today()->subMonths($i)->startOfMonth(), 'ends_on' => today()->subMonths($i)->startOfMonth(), 'status' => 'returned', 'subtotal' => (1800000 + $i * 270000), 'total' => (1800000 + $i * 270000), 'returned_at' => today()->subMonths($i)->addDay()]);
                $b->items()->create(['equipment_id' => 1, 'name' => 'Cross-back oak chair', 'quantity' => 100 + $i * 15, 'daily_rate' => 18000, 'days' => 1, 'total' => $b->total]);
                $b->payments()->create(['user_id' => $user->id, 'kind' => 'rental', 'amount' => $b->total, 'method' => 'bank', 'paid_on' => today()->subMonths($i)->startOfMonth()]);
            }
        });
        $this->command?->info('Demo administrator: admin@gather.test');
        $this->command?->info('Temporary password: '.$password);
        $this->command?->warn('Change this generated password at first sign-in. Sample records are fictional.');
    }
}
