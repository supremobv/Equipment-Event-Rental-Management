<?php

namespace Database\Seeders;

use App\Models\Category;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Setting::firstOrCreate(['id' => 1], ['business_name' => 'Gather Rentals', 'tagline' => 'Great events start here.', 'email' => 'hello@gatherrentals.example', 'phone' => '+63 917 555 0100', 'address' => '123 Event Avenue, Quezon City, Philippines', 'terms' => 'Equipment must be returned in the condition received. Rental days include both pickup and return dates. Late or damage fees are assessed at return. Security deposits are refundable after inspection. Contact us promptly for changes or cancellations.']);
        foreach (['Tables & chairs', 'Tents & staging', 'Audio & visual', 'Lighting', 'Tableware & decor'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
