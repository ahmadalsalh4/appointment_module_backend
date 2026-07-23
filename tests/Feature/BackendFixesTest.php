<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Person;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Statuses table setup
        Status::create(['id' => Status::PENDING, 'name' => 'pending']);
        Status::create(['id' => Status::CONFIRMED, 'name' => 'confirmed']);
        Status::create(['id' => Status::COMPLETED, 'name' => 'completed']);
        Status::create(['id' => Status::CANCELLED, 'name' => 'cancelled']);
    }

    public function test_booking_appointment_outside_working_hours_fails()
    {
        $personAdmin = Person::create(['name' => 'Admin', 'surname' => 'One', 'phone_number' => '111']);
        $admin = Admin::create(['person_id' => $personAdmin->id, 'email' => 'admin@test.com', 'password' => 'password']);

        $category = Category::create(['name' => 'Haircuts']);

        $personStaff = Person::create(['name' => 'Staff', 'surname' => 'One', 'phone_number' => '222']);
        $staff = Staff::create(['person_id' => $personStaff->id, 'admin_id' => $admin->id, 'job_title' => 'Barber', 'catagory_id' => $category->id, 'email' => 'staff@test.com', 'password' => 'password']);

        $personCustomer = Person::create(['name' => 'Customer', 'surname' => 'One', 'phone_number' => '333']);
        $customer = Customer::create(['person_id' => $personCustomer->id, 'email' => 'customer@test.com', 'password' => 'password']);

        $service = Service::create(['catagory_id' => $category->id, 'name' => 'Standard Cut', 'duration' => 30]);

        // Attempt 03:00 AM booking (outside working hours)
        $nextDate = now()->addDays(2)->format('Y-m-d');
        $response = $this->actingAs($customer, 'customer')->postJson('/api/appointments', [
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'start_date' => "{$nextDate} 03:00:00",
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Seçilen saat aralığı personelin mesai saatleri (09:00-12:00, 13:00-17:00) dışındadır.']);
    }

    public function test_booking_appointment_spanning_lunch_break_fails()
    {
        $personAdmin = Person::create(['name' => 'Admin', 'surname' => 'One', 'phone_number' => '111']);
        $admin = Admin::create(['person_id' => $personAdmin->id, 'email' => 'admin@test.com', 'password' => 'password']);

        $category = Category::create(['name' => 'Haircuts']);

        $personStaff = Person::create(['name' => 'Staff', 'surname' => 'One', 'phone_number' => '222']);
        $staff = Staff::create(['person_id' => $personStaff->id, 'admin_id' => $admin->id, 'job_title' => 'Barber', 'catagory_id' => $category->id, 'email' => 'staff@test.com', 'password' => 'password']);

        $personCustomer = Person::create(['name' => 'Customer', 'surname' => 'One', 'phone_number' => '333']);
        $customer = Customer::create(['person_id' => $personCustomer->id, 'email' => 'customer@test.com', 'password' => 'password']);

        $service = Service::create(['catagory_id' => $category->id, 'name' => 'Long Hair Cut', 'duration' => 45]);

        // 11:45 AM start with 45 min duration ends at 12:30 PM (spans lunch break 12:00-13:00)
        $nextDate = now()->addDays(2)->format('Y-m-d');
        $response = $this->actingAs($customer, 'customer')->postJson('/api/appointments', [
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'start_date' => "{$nextDate} 11:45:00",
        ]);

        $response->assertStatus(422);
    }

    public function test_booking_appointment_within_working_hours_succeeds()
    {
        $personAdmin = Person::create(['name' => 'Admin', 'surname' => 'One', 'phone_number' => '111']);
        $admin = Admin::create(['person_id' => $personAdmin->id, 'email' => 'admin@test.com', 'password' => 'password']);

        $category = Category::create(['name' => 'Haircuts']);

        $personStaff = Person::create(['name' => 'Staff', 'surname' => 'One', 'phone_number' => '222']);
        $staff = Staff::create(['person_id' => $personStaff->id, 'admin_id' => $admin->id, 'job_title' => 'Barber', 'catagory_id' => $category->id, 'email' => 'staff@test.com', 'password' => 'password']);

        $personCustomer = Person::create(['name' => 'Customer', 'surname' => 'One', 'phone_number' => '333']);
        $customer = Customer::create(['person_id' => $personCustomer->id, 'email' => 'customer@test.com', 'password' => 'password']);

        $service = Service::create(['catagory_id' => $category->id, 'name' => 'Quick Cut', 'duration' => 30]);

        $nextDate = now()->addDays(2)->format('Y-m-d');
        $response = $this->actingAs($customer, 'customer')->postJson('/api/appointments', [
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'start_date' => "{$nextDate} 10:00:00",
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['staff_id' => $staff->id, 'state_id' => Status::PENDING]);
    }

    public function test_admin_staff_isolation()
    {
        $admin1 = Admin::create(['person_id' => Person::create(['name' => 'A1', 'surname' => 'S1'])->id, 'email' => 'a1@test.com', 'password' => 'password']);
        $admin2 = Admin::create(['person_id' => Person::create(['name' => 'A2', 'surname' => 'S2'])->id, 'email' => 'a2@test.com', 'password' => 'password']);

        $staff1 = Staff::create(['person_id' => Person::create(['name' => 'St1', 'surname' => 'Su1'])->id, 'admin_id' => $admin1->id, 'job_title' => 'Doctor', 'email' => 'st1@test.com', 'password' => 'password']);
        $staff2 = Staff::create(['person_id' => Person::create(['name' => 'St2', 'surname' => 'Su2'])->id, 'admin_id' => $admin2->id, 'job_title' => 'Nurse', 'email' => 'st2@test.com', 'password' => 'password']);

        // Admin 1 listing staff should only get staff1
        $resIndex = $this->actingAs($admin1, 'admin')->getJson('/api/staff-members');
        $resIndex->assertStatus(200)->assertJsonCount(1);
        $resIndex->assertJsonFragment(['id' => $staff1->id]);
        $resIndex->assertJsonMissing(['id' => $staff2->id]);

        // Admin 1 attempting to view staff2 should be 403
        $resShow = $this->actingAs($admin1, 'admin')->getJson("/api/staff-members/{$staff2->id}");
        $resShow->assertStatus(403);
    }

    public function test_profile_update_endpoints()
    {
        $person = Person::create(['name' => 'OldName', 'surname' => 'OldSurname', 'phone_number' => '12345']);
        $customer = Customer::create(['person_id' => $person->id, 'email' => 'old@test.com', 'password' => 'password']);

        $response = $this->actingAs($customer, 'customer')->putJson('/api/customer/profile', [
            'name' => 'NewName',
            'surname' => 'NewSurname',
            'phone_number' => '99999',
            'email' => 'new@test.com',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'email' => 'new@test.com']);
        $this->assertDatabaseHas('persons', ['id' => $person->id, 'name' => 'NewName', 'surname' => 'NewSurname', 'phone_number' => '99999']);
    }

    public function test_cancellation_of_completed_appointment_fails()
    {
        $admin = Admin::create(['person_id' => Person::create(['name' => 'A1', 'surname' => 'S1'])->id, 'email' => 'a1@test.com', 'password' => 'password']);
        $category = Category::create(['name' => 'Cat1']);
        $staff = Staff::create(['person_id' => Person::create(['name' => 'St1', 'surname' => 'Su1'])->id, 'admin_id' => $admin->id, 'job_title' => 'Doctor', 'catagory_id' => $category->id, 'email' => 'st1@test.com', 'password' => 'password']);
        $customer = Customer::create(['person_id' => Person::create(['name' => 'C1', 'surname' => 'Cu1'])->id, 'email' => 'c1@test.com', 'password' => 'password']);
        $service = Service::create(['catagory_id' => $category->id, 'name' => 'S1', 'duration' => 30]);

        $appointment = Appointment::create([
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'state_id' => Status::COMPLETED,
            'start_date' => now()->subDay(),
            'end_date' => now()->subDay()->addMinutes(30),
        ]);

        $response = $this->actingAs($customer, 'customer')->patchJson("/api/appointments/{$appointment->id}/cancel");
        $response->assertStatus(422);
    }
}
