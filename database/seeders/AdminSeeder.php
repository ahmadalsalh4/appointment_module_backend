<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Person;
use App\Models\Admin;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $person = Person::firstOrCreate(
            ['phone_number' => '5550000000'],
            [
                'name' => 'Admin',
                'surname' => 'Yönetici',
                'phone_number' => '5550000000',
            ]
        );

        Admin::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'person_id' => $person->id,
                'password' => 'admin123', // Model'de 'hashed' cast var, otomatik hashlenir
            ]
        );
    }
}
