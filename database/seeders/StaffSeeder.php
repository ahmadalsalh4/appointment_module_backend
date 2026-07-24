<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Person;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::first();
        if (!$admin) {
            $this->command->warn('Admin bulunamadı, StaffSeeder atlanıyor.');
            return;
        }

        $staffByCategory = [
            'Eğitim' => [
                ['name' => 'Selin', 'surname' => 'Aydın', 'email' => 'selin@test.com', 'job_title' => 'Matematik Öğretmeni'],
                ['name' => 'Murat', 'surname' => 'Polat', 'email' => 'murat@test.com', 'job_title' => 'İngilizce Eğitmeni'],
            ],
            'Yazılım' => [
                ['name' => 'Ahmet', 'surname' => 'Korkmaz', 'email' => 'ahmet@test.com', 'job_title' => 'Yazılım Geliştirici'],
                ['name' => 'Burcu', 'surname' => 'Erdoğan', 'email' => 'burcu@test.com', 'job_title' => 'UI/UX Tasarımcısı'],
            ],
            'Temizlik' => [
                ['name' => 'Hüseyin', 'surname' => 'Aksoy', 'email' => 'huseyin@test.com', 'job_title' => 'Temizlik Uzmanı'],
                ['name' => 'Sevgi', 'surname' => 'Yıldırım', 'email' => 'sevgi@test.com', 'job_title' => 'Ev Temizlik Personeli'],
            ],
        ];

        foreach ($staffByCategory as $categoryName => $members) {
            $category = Category::where('name', $categoryName)->first();
            if (!$category) {
                continue;
            }
            foreach ($members as $member) {
                $person = Person::firstOrCreate(
                    ['phone_number' => '555' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)],
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
                        'password' => 'staff123',
                        'admin_id' => $admin->id,
                        'catagory_id' => $category->id,
                    ],
                );
            }
        }
    }
}
