<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Person;
use App\Models\Service as ServiceModel;
use App\Models\Staff;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackendFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Status::create(['id' => Status::PENDING, 'name' => 'pending']);
        Status::create(['id' => Status::CONFIRMED, 'name' => 'confirmed']);
        Status::create(['id' => Status::COMPLETED, 'name' => 'completed']);
        Status::create(['id' => Status::CANCELLED, 'name' => 'cancelled']);
    }

    protected function makeAdmin(string $email = 'admin@test.com'): Admin
    {
        return Admin::firstOrCreate(
            ['email' => $email],
            [
                'person_id' => Person::create([
                    'name' => 'Admin',
                    'surname' => 'One',
                    'phone_number' => uniqid('phone-', true),
                ])->id,
                'password' => 'password',
            ],
        );
    }

    protected function makeStaff(Admin $admin, Category $category, string $email = 'staff@test.com'): Staff
    {
        return Staff::firstOrCreate(
            ['email' => $email],
            [
                'person_id' => Person::create([
                    'name' => 'Staff',
                    'surname' => 'One',
                    'phone_number' => uniqid('phone-staff-', true),
                ])->id,
                'admin_id' => $admin->id,
                'job_title' => 'Barber',
                'catagory_id' => $category->id,
                'password' => 'password',
            ],
        );
    }

    protected function makeCustomer(string $email = 'customer@test.com'): Customer
    {
        return Customer::firstOrCreate(
            ['email' => $email],
            [
                'person_id' => Person::create([
                    'name' => 'Customer',
                    'surname' => 'One',
                    'phone_number' => uniqid('phone-cust-', true),
                ])->id,
                'password' => 'password',
            ],
        );
    }

    public function test_booking_appointment_outside_working_hours_fails()
    {
        $admin = $this->makeAdmin();
        $category = Category::create(['name' => 'Haircuts']);
        $staff = $this->makeStaff($admin, $category);
        $customer = $this->makeCustomer();
        $service = ServiceModel::create(['catagory_id' => $category->id, 'name' => 'Standard Cut', 'duration' => 30]);

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
        $admin = $this->makeAdmin();
        $category = Category::create(['name' => 'Haircuts']);
        $staff = $this->makeStaff($admin, $category);
        $customer = $this->makeCustomer();
        $service = ServiceModel::create(['catagory_id' => $category->id, 'name' => 'Long Hair Cut', 'duration' => 45]);

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
        $admin = $this->makeAdmin();
        $category = Category::create(['name' => 'Haircuts']);
        $staff = $this->makeStaff($admin, $category);
        $customer = $this->makeCustomer();
        $service = ServiceModel::create(['catagory_id' => $category->id, 'name' => 'Quick Cut', 'duration' => 30]);

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
        $admin1 = $this->makeAdmin('a1@test.com');
        $admin2 = $this->makeAdmin('a2@test.com');

        $category = Category::create(['name' => 'Cat1']);
        $staff1 = $this->makeStaff($admin1, $category, 'st1@test.com');
        $staff2 = $this->makeStaff($admin2, $category, 'st2@test.com');

        $resIndex = $this->actingAs($admin1, 'admin')->getJson('/api/staff-members');
        $resIndex->assertStatus(200)->assertJsonCount(1, 'data');
        $resIndex->assertJsonFragment(['id' => $staff1->id]);
        $resIndex->assertJsonMissing(['id' => $staff2->id]);

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
        $admin = $this->makeAdmin('a1@test.com');
        $category = Category::create(['name' => 'Cat1']);
        $staff = $this->makeStaff($admin, $category, 'st1@test.com');
        $customer = $this->makeCustomer('c1@test.com');
        $service = ServiceModel::create(['catagory_id' => $category->id, 'name' => 'S1', 'duration' => 30]);

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

    public function test_service_name_search_escapes_like_wildcards()
    {
        $admin = $this->makeAdmin();
        $category = Category::create(['name' => 'Haircuts']);

        // Contains a literal '%' character; only this should match the literal search "10%off".
        ServiceModel::create(['catagory_id' => $category->id, 'name' => '10%off', 'duration' => 30]);
        // Has an underscore that would match an unescaped '%' wildcard if escape were broken.
        ServiceModel::create(['catagory_id' => $category->id, 'name' => '10XXoff', 'duration' => 30]);
        // Has a literal '_' character; would match an unescaped '_' wildcard if escape were broken.
        ServiceModel::create(['catagory_id' => $category->id, 'name' => '10_off', 'duration' => 30]);

        $exact = $this->actingAs($admin, 'admin')
            ->getJson('/api/services?name=' . urlencode('10%off'))
            ->assertStatus(200);

        $names = array_column($exact->json('data'), 'name');
        $this->assertContains('10%off', $names, 'Literal % should match itself');
        $this->assertNotContains('10XXoff', $names, 'Wildcard % must not match arbitrary text');
        $this->assertNotContains('10_off', $names, 'Wildcard _ must not match arbitrary text');
    }

    public function test_category_name_search_escapes_like_wildcards()
    {
        $admin = $this->makeAdmin();
        Category::create(['name' => 'A_B']);
        Category::create(['name' => 'AxB']);

        $resp = $this->actingAs($admin, 'admin')
            ->getJson('/api/categories?name=' . urlencode('A_B'))
            ->assertStatus(200);

        $names = array_column($resp->json('data'), 'name');
        $this->assertContains('A_B', $names, 'Literal _ should match itself');
        $this->assertNotContains('AxB', $names, 'Literal _ should not match x');
    }

    public function test_staff_name_search_uses_name_or_surname_within_same_person()
    {
        $admin = $this->makeAdmin();
        $category = Category::create(['name' => 'Haircuts']);

        $person = Person::create(['name' => 'Ayşe', 'surname' => 'Yılmaz', 'phone_number' => uniqid('p-', true)]);
        Staff::create([
            'person_id' => $person->id,
            'admin_id' => $admin->id,
            'job_title' => 'Barber',
            'catagory_id' => $category->id,
            'email' => 'ayse@test.com',
            'password' => 'password',
        ]);

        $unrelated = Person::create(['name' => 'Mehmet', 'surname' => 'Yılmaz', 'phone_number' => uniqid('p-', true)]);
        Staff::create([
            'person_id' => $unrelated->id,
            'admin_id' => $admin->id,
            'job_title' => 'Barber',
            'catagory_id' => $category->id,
            'email' => 'mehmet@test.com',
            'password' => 'password',
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->getJson('/api/staff-members?name=' . urlencode('Yılmaz'))
            ->assertStatus(200);

        $emails = array_column($resp->json('data'), 'email');
        sort($emails);
        $this->assertSame(['ayse@test.com', 'mehmet@test.com'], $emails, 'OR within same person should match both Ayşe (name) and Mehmet (surname)');

        $resp2 = $this->actingAs($admin, 'admin')
            ->getJson('/api/staff-members?name=' . urlencode('Ayşe'))
            ->assertStatus(200);

        $emails2 = array_column($resp2->json('data'), 'email');
        $this->assertSame(['ayse@test.com'], $emails2, 'Name-only search should not include unrelated people with the same surname');
    }

    public function test_unified_login_returns_other_roles_for_multi_role_user()
    {
        $admin = $this->makeAdmin();
        $category = Category::create(['name' => 'Haircuts']);

        $person = Person::create(['name' => 'Multi', 'surname' => 'Role', 'phone_number' => uniqid('p-', true)]);
        Customer::create(['person_id' => $person->id, 'email' => 'multi-x@test.com', 'password' => 'password']);
        Staff::create([
            'person_id' => $person->id,
            'admin_id' => $admin->id,
            'job_title' => 'Barber',
            'catagory_id' => $category->id,
            'email' => 'multi-x@test.com',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'multi-x@test.com',
            'password' => 'password',
        ])->assertStatus(200);

        $role = $login->json('role');
        $other = $login->json('other_roles');
        $this->assertSame('customer', $role, 'Customer row is checked first in the cascade');
        $this->assertContains('staff', $other);

        $login2 = $this->postJson('/api/login', [
            'email' => 'multi-x@test.com',
            'password' => 'password',
        ])->assertStatus(200);
        $this->assertSame('customer', $login2->json('role'));

        $token = $login2->json('token');
        $meRoles = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/me/roles')
            ->assertStatus(200);
        $this->assertSame('customer', $meRoles->json('current_role'));
        $this->assertContains('staff', $meRoles->json('other_roles'));

        $switch = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/switch-role', [
                'role' => 'staff',
                'password' => 'password',
            ])->assertStatus(200);
        $this->assertSame('staff', $switch->json('role'));
    }
}
