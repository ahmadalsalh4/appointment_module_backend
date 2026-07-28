<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Person;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::first();
        if (! $admin) {
            $this->command->warn('Admin bulunamadı, StaffSeeder atlanıyor.');

            return;
        }

        // NOTE: phone numbers are stable per member so re-running the
        // seeder doesn't spawn new Person rows every time.
        $staffByCategory = [
            'Eğitim' => [
                ['name' => 'Selin', 'surname' => 'Aydın', 'email' => 'selin@test.com', 'phone' => '5551000001', 'job_title' => 'Matematik Öğretmeni'],
                ['name' => 'Murat', 'surname' => 'Polat', 'email' => 'murat@test.com', 'phone' => '5551000002', 'job_title' => 'İngilizce Eğitmeni'],
            ],
            'Yazılım' => [
                ['name' => 'Ahmet', 'surname' => 'Korkmaz', 'email' => 'ahmet@test.com', 'phone' => '5552000001', 'job_title' => 'Yazılım Geliştirici'],
                ['name' => 'Burcu', 'surname' => 'Erdoğan', 'email' => 'burcu@test.com', 'phone' => '5552000002', 'job_title' => 'UI/UX Tasarımcısı'],
            ],
            'Temizlik' => [
                ['name' => 'Hüseyin', 'surname' => 'Aksoy', 'email' => 'huseyin@test.com', 'phone' => '5553000001', 'job_title' => 'Temizlik Uzmanı'],
                ['name' => 'Sevgi', 'surname' => 'Yıldırım', 'email' => 'sevgi@test.com', 'phone' => '5553000002', 'job_title' => 'Ev Temizlik Personeli'],
            ],
        ];

        foreach ($staffByCategory as $categoryName => $members) {
            $category = Category::where('name', $categoryName)->first();
            if (! $category) {
                continue;
            }
            foreach ($members as $member) {
                $person = Person::firstOrCreate(
                    ['phone_number' => $member['phone']],
                    [
                        'name' => $member['name'],
                        'surname' => $member['surname'],
                    ],
                );
                Staff::firstOrCreate(
                    ['email' => $member['email']],
                    [
                        'person_id' => $person->id,
                        'job_title' => $member['job_title'],
                        'password' => Hash::make('staff123'),
                        'admin_id' => $admin->id,
                        // NOTE: `catagory_id` typo — see Staff model.
                        'catagory_id' => $category->id,
                    ],
                );
            }
        }
    }
}
