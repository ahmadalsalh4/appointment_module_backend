<?php

namespace App\Http\Controllers;

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

        $validated = $request->validate([
            'email' => ['sometimes', 'email', Rule::unique('staff', 'email')->ignore($staff->id)],
            'password' => ['sometimes', 'string', 'min:6', 'confirmed'],
            'job_title' => ['sometimes', 'string', 'max:100'],
            'catagory_id' => ['nullable', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:100'],
            'surname' => ['sometimes', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($validated, $staff) {
            $staffData = array_intersect_key($validated, array_flip(['email', 'password', 'job_title', 'catagory_id']));
            if (!empty($staffData)) {
                $staff->update($staffData);
            }

            $personData = array_intersect_key($validated, array_flip(['name', 'surname', 'phone_number']));
            if (!empty($personData)) {
                $staff->person->update($personData);
            }
        });

        return response()->json($staff->load(['person', 'managingAdmin.person', 'category']));
    }
}
