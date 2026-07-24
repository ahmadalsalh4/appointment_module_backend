<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Person;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name' => 'Ahmad', 'surname' => 'Alsaleh', 'email' => 'ahmad@test.com', 'phone_number' => '5551111111'],
            ['name' => 'Elif', 'surname' => 'Arslan', 'email' => 'elif@test.com', 'phone_number' => '5552222222'],
            ['name' => 'Burak', 'surname' => 'Doğan', 'email' => 'burak@test.com', 'phone_number' => '5553333333'],
        ];

        foreach ($customers as $c) {
            $person = Person::firstOrCreate(
                ['phone_number' => $c['phone_number']],
                ['name' => $c['name'], 'surname' => $c['surname']],
            );
            Customer::firstOrCreate(
                ['email' => $c['email']],
                [
                    'person_id' => $person->id,
                    'password' => 'customer123',
                ],
            );
        }
    }
}
