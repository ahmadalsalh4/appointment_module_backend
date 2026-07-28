<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerProfileController extends Controller
{
    public function show(Request $request)
    {
        $customer = $request->user();

        return response()->json($customer->load('person'));
    }

    public function update(Request $request)
    {
        $customer = $request->user();

        $validated = $request->validate([
            'email' => ['sometimes', 'email', Rule::unique('customers', 'email')->ignore($customer->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'name' => ['sometimes', 'string', 'max:100'],
            'surname' => ['sometimes', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($validated, $customer) {
            $customerData = array_intersect_key($validated, array_flip(['email', 'password']));
            if (! empty($customerData)) {
                $customer->update($customerData);
            }

            $personData = array_intersect_key($validated, array_flip(['name', 'surname', 'phone_number']));
            if (! empty($personData) && $customer->person) {
                $customer->person->update($personData);
            }
        });

        return response()->json($customer->load('person'));
    }
}
