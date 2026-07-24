<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $staff = Staff::all();

        if ($customers->isEmpty() || $staff->isEmpty()) {
            $this->command->warn('Müşteri veya personel bulunamadı, AppointmentSeeder atlanıyor.');
            return;
        }

        // [staff_email, customer_email, service_name, days_from_now, hour, minute, status_id]
        $plan = [
            // Eğitim
            ['selin@test.com', 'ahmad@test.com', 'Matematik Dersi', 1, 10, 0, Status::PENDING],
            ['selin@test.com', 'elif@test.com', 'Matematik Dersi', 2, 11, 0, Status::CONFIRMED],
            ['murat@test.com', 'burak@test.com', 'İngilizce Dersi', 3, 14, 0, Status::PENDING],
            ['murat@test.com', 'ahmad@test.com', 'Yazılım Eğitimi', 5, 15, 0, Status::CONFIRMED],

            // Yazılım
            ['ahmet@test.com', 'elif@test.com', 'Web Sitesi Geliştirme', 1, 9, 0, Status::CONFIRMED],
            ['ahmet@test.com', 'burak@test.com', 'Mobil Uygulama Geliştirme', 4, 13, 0, Status::PENDING],
            ['burcu@test.com', 'ahmad@test.com', 'SEO Danışmanlığı', 6, 11, 0, Status::PENDING],

            // Temizlik
            ['huseyin@test.com', 'elif@test.com', 'Ev Temizliği', 2, 9, 0, Status::CONFIRMED],
            ['sevgi@test.com', 'burak@test.com', 'Ofis Temizliği', 3, 14, 0, Status::PENDING],
            ['sevgi@test.com', 'ahmad@test.com', 'Halı Yıkama', 7, 10, 0, Status::PENDING],
        ];

        foreach ($plan as [$staffEmail, $customerEmail, $serviceName, $days, $hour, $minute, $statusId]) {
            $s = Staff::where('email', $staffEmail)->first();
            $c = Customer::where('email', $customerEmail)->first();
            if (!$s || !$c) {
                continue;
            }
            $service = Service::where('name', $serviceName)
                ->where('catagory_id', $s->catagory_id)
                ->first();
            if (!$service) {
                continue;
            }

            $start = Carbon::now()
                ->addDays($days)
                ->setTime($hour, $minute, 0);
            $end = $start->copy()->addMinutes($service->duration);

            Appointment::firstOrCreate(
                [
                    'staff_id' => $s->id,
                    'customer_id' => $c->id,
                    'service_id' => $service->id,
                    'start_date' => $start,
                ],
                [
                    'state_id' => $statusId,
                    'end_date' => $end,
                ],
            );
        }
    }
}
