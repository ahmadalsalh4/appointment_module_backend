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
            'job_title' => 'required|string|max:100',
            'email' => 'required|email|unique:staff,email',   // ✅ staff'ın kendi email'i
            'password' => 'required|string|min:6',
        ]);

        $staff = DB::transaction(function () use ($validated, $request) {
            $person = Person::create([
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'phone_number' => $validated['phone_number'] ?? null,
            ]);

            return Staff::create([
                'person_id' => $person->id,
                'job_title' => $validated['job_title'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'admin_id' => $request->user()->id,   // ✅ route zaten auth:admin, bu her zaman doğru admin
            ]);
        });

        return response()->json($staff->load('person'), 201);
    }

    public function show(Staff $staff_member)
    {
        return response()->json($staff_member->load(['person', 'managingAdmin']));   // ✅ adminProfile → managingAdmin
    }


    public function update(Request $request, Staff $staff_member)
    {
        $validated = $request->validate([
            'job_title' => 'sometimes|string|max:100',
            'job_email' => 'sometimes|email|unique:staff,job_email,' . $staff_member->id,
        ]);

        $staff_member->update($validated);

        return response()->json($staff_member);
    }

    public function destroy(Staff $staff_member)
    {
        $staff_member->delete();
        return response()->json(['message' => 'Personel silindi']);
    }
}
