<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffProfileController extends Controller
{
    public function show(Request $request)
    {
        $staff = $request->user();

        return response()->json($staff->load(['person', 'managingAdmin.person', 'category']));
    }

    public function update(Request $request)
    {
        $staff = $request->user();

        // NOTE: `category_id` is intentionally NOT editable on the
        // self-service endpoint. Staff cannot reassign their own
        // category — only admins can, via PUT /staff-members/{id}.
        $validated = $request->validate([
            'email' => ['sometimes', 'email', Rule::unique('staff', 'email')->ignore($staff->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'job_title' => ['sometimes', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:100'],
            'surname' => ['sometimes', 'string', 'max:100'],
            'phone_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('persons', 'phone_number')
                    ->ignore($staff->person?->id)
                    ->where(fn ($q) => $q->whereNotNull('phone_number')),
            ],
        ]);

        try {
            DB::transaction(function () use ($validated, $staff) {
                $staffData = array_intersect_key($validated, array_flip(['email', 'password', 'job_title']));
                if (! empty($staffData)) {
                    $staff->update($staffData);
                }

                $personData = array_intersect_key($validated, array_flip(['name', 'surname', 'phone_number']));
                if (! empty($personData) && $staff->person) {
                    $staff->person->update($personData);
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

        return response()->json($staff->load(['person', 'managingAdmin.person', 'category']));
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
