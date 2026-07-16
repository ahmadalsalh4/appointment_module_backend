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
            'email' => 'required|email|unique:persons,email',
            'password' => 'required|string|min:6',
        ]);

        $customer = DB::transaction(function () use ($validated) {
            $person = Person::create([
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'phone_number' => $validated['phone_number'] ?? null,
                'email' => $validated['email'],
            ]);

            return Customer::create([
                'person_id' => $person->id,
                'password' => $validated['password'], // Model'de 'hashed' cast var, otomatik hashlenir
            ]);
        });

        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json([
            'customer' => $customer->load('person'),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $customer = Customer::whereHas('person', function ($q) use ($request) {
            $q->where('email', $request->email);
        })->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Bilgiler hatalı.'],
            ]);
        }

        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json([
            'customer' => $customer->load('person'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Çıkış yapıldı']);
    }
}
