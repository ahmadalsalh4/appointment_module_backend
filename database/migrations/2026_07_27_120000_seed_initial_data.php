<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $categories = ['Eğitim', 'Yazılım', 'Temizlik'];
        $categoryIds = [];

        foreach ($categories as $name) {
            $existing = DB::table('categories')->where('name', $name)->first();
            if ($existing) {
                $categoryIds[$name] = $existing->id;
            } else {
                $categoryIds[$name] = DB::table('categories')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $adminPerson = DB::table('persons')->where('phone_number', '5550000000')->first();
        if ($adminPerson) {
            $adminPersonId = $adminPerson->id;
        } else {
            $adminPersonId = DB::table('persons')->insertGetId([
                'name' => 'Admin',
                'surname' => 'Yönetici',
                'phone_number' => '5550000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingAdmin = DB::table('admin')->where('email', 'admin@test.com')->first();
        if ($existingAdmin) {
            $adminId = $existingAdmin->id;
        } else {
            $adminId = DB::table('admin')->insertGetId([
                'person_id' => $adminPersonId,
                'email' => 'admin@test.com',
                'password' => Hash::make('admin123'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $staffPerson = DB::table('persons')->where('phone_number', '5551111111')->first();
        if ($staffPerson) {
            $staffPersonId = $staffPerson->id;
        } else {
            $staffPersonId = DB::table('persons')->insertGetId([
                'name' => 'Selin',
                'surname' => 'Aydın',
                'phone_number' => '5551111111',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingStaff = DB::table('staff')->where('email', 'selin@test.com')->first();
        if (!$existingStaff) {
            DB::table('staff')->insert([
                'person_id' => $staffPersonId,
                'job_title' => 'Matematik Öğretmeni',
                'email' => 'selin@test.com',
                'password' => Hash::make('staff123'),
                'admin_id' => $adminId,
                'catagory_id' => $categoryIds['Eğitim'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('staff')->where('email', 'selin@test.com')->delete();
        DB::table('persons')->where('phone_number', '5551111111')->delete();
        DB::table('admin')->where('email', 'admin@test.com')->delete();
        DB::table('persons')->where('phone_number', '5550000000')->delete();
        DB::table('categories')->whereIn('name', ['Eğitim', 'Yazılım', 'Temizlik'])->delete();
    }
};
