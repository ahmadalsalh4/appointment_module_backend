<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminProfileController extends Controller
{
    public function show(Request $request)
    {
        $admin = $request->user();

        return response()->json($admin->load('person'));
    }

    public function update(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'email' => ['sometimes', 'email', Rule::unique('admin', 'email')->ignore($admin->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'name' => ['sometimes', 'string', 'max:100'],
            'surname' => ['sometimes', 'string', 'max:100'],
            'phone_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('persons', 'phone_number')
                    ->ignore($admin->person?->id)
                    ->where(fn ($q) => $q->whereNotNull('phone_number')),
            ],
        ]);

        try {
            DB::transaction(function () use ($validated, $admin) {
                $adminData = array_intersect_key($validated, array_flip(['email', 'password']));
                if (! empty($adminData)) {
                    $admin->update($adminData);
                }

                $personData = array_intersect_key($validated, array_flip(['name', 'surname', 'phone_number']));
                if (! empty($personData) && $admin->person) {
                    $admin->person->update($personData);
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

        return response()->json($admin->load('person'));
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
