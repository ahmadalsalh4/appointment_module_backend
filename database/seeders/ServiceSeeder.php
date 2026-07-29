<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            'Eğitim' => [
                ['name' => 'Matematik Dersi', 'duration' => 60],
                ['name' => 'İngilizce Dersi', 'duration' => 60],
                ['name' => 'Yazılım Eğitimi', 'duration' => 90],
            ],
            'Yazılım' => [
                ['name' => 'Web Sitesi Geliştirme', 'duration' => 120],
                ['name' => 'Mobil Uygulama Geliştirme', 'duration' => 120],
                ['name' => 'SEO Danışmanlığı', 'duration' => 60],
            ],
            'Temizlik' => [
                ['name' => 'Ev Temizliği', 'duration' => 120],
                ['name' => 'Ofis Temizliği', 'duration' => 90],
                ['name' => 'Halı Yıkama', 'duration' => 60],
            ],
        ];

        foreach ($services as $categoryName => $items) {
            $category = Category::where('name', $categoryName)->first();
            if (! $category) {
                continue;
            }
            foreach ($items as $item) {
                Service::firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $item['name'],
                    ],
                    ['duration' => $item['duration']],
                );
            }
        }
    }
}
