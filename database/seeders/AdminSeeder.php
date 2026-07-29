<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Person;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate so subsequent seed runs refresh name/surname/phone
        // instead of silently leaving stale values from firstOrCreate.
        $person = Person::updateOrCreate(
            ['phone_number' => '5550000000'],
            [
                'name' => 'Admin',
                'surname' => 'Yönetici',
            ]
        );

        Admin::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'person_id' => $person->id,
                // Explicit Hash::make instead of relying on the 'hashed'
                // cast (works, but the cast hides the value from log
                // diffs and seeders — being explicit is easier to audit).
                'password' => Hash::make('admin123'),
            ]
        );
    }
}