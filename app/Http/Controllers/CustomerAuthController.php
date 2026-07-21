<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'surname' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'required|email|unique:customers,email',   // ✅ customers tablosuna göre
            'password' => 'required|string|min:6',
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

        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json(['customer' => $customer->load('person'), 'token' => $token, 'role' => 'customer'], 201);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $customer = Customer::where('email', $request->email)->first();   // ✅ doğrudan customer'dan

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages(['email' => ['Bilgiler hatalı.']]);
        }

        $token = $customer->createToken('customer-token')->plainTextToken;
        return response()->json(['customer' => $customer->load('person'), 'token' => $token, 'role' => 'customer']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Çıkış yapıldı']);
    }
}
