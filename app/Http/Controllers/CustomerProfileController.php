<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Database\QueryException;
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
            'phone_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('persons', 'phone_number')
                    ->ignore($customer->person?->id)
                    ->where(fn ($q) => $q->whereNotNull('phone_number')),
            ],
        ]);

        try {
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
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return response()->json([
                    'message' => 'Telefon numarası başka bir kullanıcıda kayıtlı.',
                    'errors' => ['phone_number' => ['Bu telefon numarası zaten kullanılıyor.']],
                ], 422);
            }
            throw $e;
        }

        return response()->json($customer->load('person'));
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return ($e->errorInfo[0] ?? null) === '23505';
        }
        if ($driver === 'mysql') {
            return ($e->errorInfo[1] ?? null) === 1062;
        }
        if ($driver === 'sqlite') {
            return str_contains($e->getMessage(), 'UNIQUE constraint failed');
        }

        return false;
    }
}
