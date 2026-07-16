<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'pending'],
            ['id' => 2, 'name' => 'confirmed'],
            ['id' => 3, 'name' => 'completed'],
            ['id' => 4, 'name' => 'cancelled'],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(['id' => $status['id']], $status);
        }
    }
}
