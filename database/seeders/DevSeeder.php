<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Opt-in seeder that creates the documented test accounts
 * (admin@test.com, staff/test.com, customer@test.com, multi@test.com).
 *
 * Never run in production or staging. Invoked automatically from
 * DatabaseSeeder when the DB_SEED_DEV environment variable is true.
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('DevSeeder: creating documented test accounts. Do NOT run against shared/production databases.');

        $this->call([
            AdminSeeder::class,
            StaffSeeder::class,
            CustomerSeeder::class,
            MultiRoleSeeder::class,
        ]);
    }
}
