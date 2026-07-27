<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'surname' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'required|email|unique:customers,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $customer = DB::transaction(function () use ($validated) {
            $person = Person::create([
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'phone_number' => $validated['phone_number'] ?? null,
            ]);

            return Customer::create([
                'person_id' => $person->id,
                'email' => $validated['email'],   // ✅ email artık customer'da
                'password' => $validated['password'],
            ]);
        });

        $token = $customer->createToken('auth-token')->plainTextToken;

        return response()->json(['customer' => $customer->load('person'), 'token' => $token, 'role' => 'customer'], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Çıkış yapıldı']);
    }
}
