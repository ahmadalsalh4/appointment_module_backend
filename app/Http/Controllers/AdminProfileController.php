<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Staff;
use App\Support\Concerns\HandlesUniqueViolation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminProfileController extends Controller
{
    use HandlesUniqueViolation;

    public function show(Request $request)
    {
        $admin = $request->user();

        return response()->json($admin->load('person'));
    }

    public function update(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'email' => [
                'sometimes', 'filled', 'email',
                Rule::unique('admin', 'email')->ignore($admin->id),
                function ($attribute, $value, $fail) {
                    if (
                        Customer::where('email', $value)->exists()
                        || Staff::where('email', $value)->exists()
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
                return $this->uniqueViolationResponse($e, defaultField: 'phone_number');
            }
            throw $e;
        }

        return response()->json($admin->load('person'));
    }
}