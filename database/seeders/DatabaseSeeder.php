<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StatusSeeder::class,
            CategorySeeder::class,
            ServiceSeeder::class,
            AppointmentSeeder::class,
        ]);

        // Role-creating seeders carry deterministic, documented test
        // credentials (e.g. admin@test.com / admin123). They must only
        // be invoked when explicitly opted-in, so a stray
        // `php artisan db:seed` cannot inject these accounts into a
        // shared or staging database. Trigger with:
        //   DB_SEED_DEV=true php artisan db:seed
        if (filter_var(env('DB_SEED_DEV', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DevSeeder::class);
        }
    }
}
