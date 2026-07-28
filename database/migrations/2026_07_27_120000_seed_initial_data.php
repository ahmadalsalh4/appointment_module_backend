<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'pending'],
            ['id' => 2, 'name' => 'confirmed'],
            ['id' => 3, 'name' => 'completed'],
            ['id' => 4, 'name' => 'cancelled'],
        ];

        foreach ($statuses as $status) {
            $existing = DB::table('statuses')->where('id', $status['id'])->first();
            if (!$existing) {
                DB::table('statuses')->insert(array_merge($status, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $categories = ['Eğitim', 'Yazılım', 'Temizlik'];

        foreach ($categories as $name) {
            $existing = DB::table('categories')->where('name', $name)->first();
            if (!$existing) {
                DB::table('categories')->insert([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('categories')->whereIn('name', ['Eğitim', 'Yazılım', 'Temizlik'])->delete();
        DB::table('statuses')->whereIn('id', [1, 2, 3, 4])->delete();
    }
};
