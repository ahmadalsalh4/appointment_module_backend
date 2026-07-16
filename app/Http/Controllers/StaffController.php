<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index()
    {
        return response()->json(Staff::with('person')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'surname' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'required|email|unique:persons,email',
            'job_title' => 'required|string|max:100',
            'job_email' => 'required|email|unique:staff,job_email',
            'password' => 'required|string|min:6',
            'admin_id' => 'nullable|exists:admin,id',
        ]);

        $staff = DB::transaction(function () use ($validated) {
            $person = Person::create([
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'phone_number' => $validated['phone_number'] ?? null,
                'email' => $validated['email'],
            ]);

            return Staff::create([
                'person_id' => $person->id,
                'job_title' => $validated['job_title'],
                'job_email' => $validated['job_email'],
                'password' => $validated['password'],
                'admin_id' => $validated['admin_id'] ?? null,
            ]);
        });

        return response()->json($staff->load('person'), 201);
    }

    public function show(Staff $staff)
    {
        return response()->json($staff->load(['person', 'adminProfile']));
    }

    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'job_title' => 'sometimes|string|max:100',
            'job_email' => 'sometimes|email|unique:staff,job_email,' . $staff->id,
            'admin_id' => 'nullable|exists:admin,id',
        ]);

        $staff->update($validated);

        return response()->json($staff);
    }

    public function destroy(Staff $staff)
    {
        $staff->delete();

        return response()->json(['message' => 'Personel silindi']);
    }
}
