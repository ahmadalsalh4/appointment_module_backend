<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Staff::where('admin_id', $request->user()->id)->with(['person', 'category'])->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'surname' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'job_title' => 'required|string|max:100',
            'email' => 'required|email|unique:staff,email',
            'password' => 'required|string|min:6',
            'catagory_id' => 'nullable|exists:catagorys,id',
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
                'admin_id' => $request->user()->id,
                'catagory_id' => $validated['catagory_id'] ?? null,
            ]);
        });

        return response()->json($staff->load(['person', 'category']), 201);
    }

    public function show(Request $request, Staff $staff_member)
    {
        if ($staff_member->admin_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu personeli görüntüleme yetkiniz yok'], 403);
        }

        return response()->json($staff_member->load(['person', 'managingAdmin', 'category']));
    }

    public function update(Request $request, Staff $staff_member)
    {
        if ($staff_member->admin_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu personeli güncelleme yetkiniz yok'], 403);
        }

        $validated = $request->validate([
            'job_title' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:staff,email,' . $staff_member->id,
            'name' => 'sometimes|string|max:100',
            'surname' => 'sometimes|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'catagory_id' => 'nullable|exists:catagorys,id',
        ]);

        DB::transaction(function () use ($validated, $staff_member) {
            $staffData = array_intersect_key($validated, array_flip(['job_title', 'email', 'catagory_id']));
            if (!empty($staffData)) {
                $staff_member->update($staffData);
            }

            $personData = array_intersect_key($validated, array_flip(['name', 'surname', 'phone_number']));
            if (!empty($personData)) {
                $staff_member->person->update($personData);
            }
        });

        return response()->json($staff_member->load(['person', 'category']));
    }

    public function destroy(Request $request, Staff $staff_member)
    {
        if ($staff_member->admin_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu personeli silme yetkiniz yok'], 403);
        }

        $staff_member->delete();
        return response()->json(['message' => 'Personel silindi']);
    }
}
