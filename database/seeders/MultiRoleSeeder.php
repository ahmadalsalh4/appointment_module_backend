<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Person;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class MultiRoleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::first();
        if (!$admin) {
            $this->command->warn('Admin bulunamadı, MultiRoleSeeder atlanıyor.');
            return;
        }

        $category = Category::where('name', 'Eğitim')->first()
            ?? Category::first();

        $email = 'multi@test.com';
        $password = 'multi123';
        $phone = '5559999999';

        $person = Person::firstOrCreate(
            ['phone_number' => $phone],
            ['name' => 'Çoklu', 'surname' => 'Rol'],
        );

        $customer = Customer::updateOrCreate(
            ['email' => $email],
            [
                'person_id' => $person->id,
                'password' => $password,
            ],
        );

        $staffCreated = false;
        if ($category) {
            $staff = Staff::updateOrCreate(
                ['email' => $email],
                [
                    'person_id' => $person->id,
                    'job_title' => 'Çoklu Rol Kullanıcısı',
                    'password' => $password,
                    'admin_id' => $admin->id,
                    'catagory_id' => $category->id,
                ],
            );
            $staffCreated = $staff->wasRecentlyCreated || $staff->wasChanged();
        } else {
            $this->command->warn('MultiRoleSeeder: hiçbir kategori bulunamadı, personel kaydı atlandı.');
        }

        if ($customer->wasRecentlyCreated || $staffCreated) {
            $this->command->info("Çoklu rol kullanıcısı hazır: {$email} / {$password}");
        } else {
            $this->command->line("Çoklu rol kullanıcısı zaten mevcut: {$email}");
        }
    }
}
