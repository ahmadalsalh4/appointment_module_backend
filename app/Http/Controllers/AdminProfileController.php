<?php

namespace App\Http\Controllers;

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
            'phone_number' => ['nullable', 'string', 'max:20'],
        ]);

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

        return response()->json($admin->load('person'));
    }
}
