<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Support\Concerns\HandlesUniqueViolation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffProfileController extends Controller
{
    use HandlesUniqueViolation;

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
            'email' => [
                'sometimes', 'filled', 'email',
                Rule::unique('staff', 'email')->ignore($staff->id),
                function ($attribute, $value, $fail) {
                    if (
                        Customer::where('email', $value)->exists()
                        || Admin::where('email', $value)->exists()
                    ) {
                        $fail('Bu email adresi zaten başka bir rolde kullanılıyor.');
                    }
                },
            ],
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
                return $this->uniqueViolationResponse($e, defaultField: 'phone_number');
            }
            throw $e;
        }

        return response()->json($staff->load(['person', 'managingAdmin.person', 'category']));
    }
}