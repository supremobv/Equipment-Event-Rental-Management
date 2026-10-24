<?php

use App\Models\Booking;
use App\Services\RentalService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Dedicated test subprocess; not a web endpoint.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
    fwrite(STDERR, 'Dedicated MySQL test database required.');
    exit(2);
}
[$script,$bookingId,$userId,$barrier] = $argv;
Auth::loginUsingId((int) $userId);
DB::listen(function ($event) use ($barrier, $bookingId) {
    if (str_contains($event->sql, '`booking_items`') && str_contains(strtolower($event->sql), 'for update')) {
        file_put_contents($barrier.'/ready-'.$bookingId, 'ready');
    }
});
try {
    app(RentalService::class)->transition(Booking::findOrFail($bookingId), 'confirm');
    echo json_encode(['result' => 'confirmed']);
} catch (ValidationException $e) {
    echo json_encode(['result' => 'rejected']);
}
