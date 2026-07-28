<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Person;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        // Cross-table email uniqueness: a customer/staff/admin may share
        // an email on purpose (multi-role users), so we use a custom rule
        // instead of Laravel's `unique:customers,email` which would allow
        // registering with an email already taken by a staff/admin.
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'surname' => 'required|string|max:100',
            'phone_number' => ['nullable', 'string', 'max:20'],
            'email' => [
                'required',
                'email',
                function ($attribute, $value, $fail) {
                    if (
                        Customer::where('email', $value)->exists()
                        || Staff::where('email', $value)->exists()
                        || Admin::where('email', $value)->exists()
                    ) {
                        $fail('Bu email adresi zaten kullanılıyor.');
                    }
                },
            ],
            'password' => 'required|string|min:8|confirmed',
        ]);

        $customer = DB::transaction(function () use ($validated) {
            $person = Person::create([
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'phone_number' => $validated['phone_number'] ?? null,
            ]);

            return Customer::create([
                'person_id' => $person->id,
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
        });

        $token = $customer->createToken('auth-token')->plainTextToken;

        return response()->json(['customer' => $customer->load('person'), 'token' => $token, 'role' => 'customer'], 201);
    }

    public function logout(Request $request)
    {
        if ($request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Çıkış yapıldı']);
    }
}
