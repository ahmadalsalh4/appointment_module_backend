<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Staff;
use App\Support\Concerns\HandlesUniqueViolation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerProfileController extends Controller
{
    use HandlesUniqueViolation;

    public function show(Request $request)
    {
        $customer = $request->user();

        return response()->json($customer->load('person'));
    }

    public function update(Request $request)
    {
        $customer = $request->user();

        $validated = $request->validate([
            'email' => [
                'sometimes', 'filled', 'email',
                Rule::unique('customers', 'email')->ignore($customer->id),
                // Cross-table email uniqueness: an existing staff/admin
                // using the same email is intentional for multi-role users,
                // but switching our email to one already used by staff/admin
                // while logged in as customer is rejected to keep the
                // login flow consistent.
                function ($attribute, $value, $fail) {
                    if (
                        Staff::where('email', $value)->exists()
                        || Admin::where('email', $value)->exists()
                    ) {
                        $fail('Bu email adresi zaten başka bir rolde kullanılıyor.');
                    }
                },
            ],
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
                return $this->uniqueViolationResponse($e, defaultField: 'phone_number');
            }
            throw $e;
        }

        return response()->json($customer->load('person'));
    }
}