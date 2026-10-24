<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Setting;
use App\Models\User;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_competing_operators_cannot_double_book_after_waiting_for_the_same_stock_lock(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Run against a dedicated MySQL database ending in _test.');
        }
        Setting::create(['business_name' => 'Concurrency test']);
        $user = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $this->actingAs($user);
        $cat = Category::create(['name' => 'Race test']);
        $e = Equipment::create(['category_id' => $cat->id, 'name' => 'Single unit', 'sku' => 'RACE', 'quantity' => 1, 'daily_rate' => 10000]);
        $c = Customer::create(['name' => 'Race customer', 'phone' => '123']);
        $data = ['customer_id' => $c->id, 'event_name' => 'Concurrent event', 'starts_on' => today()->toDateString(), 'ends_on' => today()->toDateString(), 'fulfillment' => 'pickup', 'discount' => '0', 'delivery_fee' => '0', 'deposit_required' => '0', 'items' => [['equipment_id' => $e->id, 'quantity' => 1]]];
        $a = app(RentalService::class)->saveQuote($data);
        $b = app(RentalService::class)->saveQuote($data);
        $barrier = sys_get_temp_dir().'/rental-race-'.bin2hex(random_bytes(8));
        mkdir($barrier);
        $config = config('database.connections.mysql');
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $config['database'], 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'] ?? '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $workers = [];
        try {
            DB::beginTransaction();
            Equipment::whereKey($e->id)->lockForUpdate()->firstOrFail();
            foreach ([$a, $b] as $booking) {
                $worker = new Process([PHP_BINARY, base_path('tests/support/confirm-worker.php'), (string) $booking->id, (string) $user->id, $barrier], base_path(), $env);
                $worker->setTimeout(15);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            while ((! file_exists($barrier.'/ready-'.$a->id) || ! file_exists($barrier.'/ready-'.$b->id)) && microtime(true) < $deadline) {
                usleep(20000);
            }
            $this->assertFileExists($barrier.'/ready-'.$a->id);
            $this->assertFileExists($barrier.'/ready-'.$b->id);
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true)['result'];
            }sort($results);
            $this->assertSame(['confirmed', 'rejected'], $results);
            $this->assertSame(1, Booking::where('status', 'confirmed')->count());
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }foreach ([$a, $b] as $booking) {
                $file = $barrier.'/ready-'.$booking->id;
                if (file_exists($file)) {
                    unlink($file);
                }
            }rmdir($barrier);
        }
    }
}
