<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Staff;
use App\Models\Person;
use App\Support\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $query = Staff::where('admin_id', $request->user()->id);

        if ($request->filled('name')) {
            $escaped = SearchHelper::likeContains($request->name);
            $query->whereHas('person', function ($q) use ($escaped) {
                $q->where(function ($q2) use ($escaped) {
                    $q2->whereRaw('name LIKE ? ' . SearchHelper::ESCAPE_CLAUSE, [$escaped])
                       ->orWhereRaw('surname LIKE ? ' . SearchHelper::ESCAPE_CLAUSE, [$escaped]);
                });
            });
        }

        if ($request->filled('email')) {
            $query->whereRaw('email LIKE ? ' . SearchHelper::ESCAPE_CLAUSE, [SearchHelper::likeContains($request->email)]);
        }

        $allowedSorts = ['id', 'job_title', 'email', 'catagory_id', 'created_at', 'name'];
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = strtolower($request->get('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        if ($sortBy === 'name') {
            $query->join('persons', 'staff.person_id', '=', 'persons.id')
                  ->orderBy('persons.name', $sortOrder)
                  ->select('staff.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));

        return response()->json(
            $query->with(['person', 'category'])->paginate($perPage)
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
            'password' => 'required|string|min:6|confirmed',
            'catagory_id' => 'nullable|exists:categories,id',
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
            'catagory_id' => 'nullable|exists:categories,id',
            'password' => 'sometimes|string|min:6|confirmed',
        ]);

        DB::transaction(function () use ($validated, $staff_member) {
            $staffData = array_intersect_key($validated, array_flip(['job_title', 'email', 'catagory_id', 'password']));
            if (!empty($staffData)) {
                $staff_member->update($staffData);
            }

            $personData = array_intersect_key($validated, array_flip(['name', 'surname', 'phone_number']));
            if (!empty($personData) && $staff_member->person) {
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

        $hasActiveAppointments = \App\Models\Appointment::where('staff_id', $staff_member->id)
            ->whereNotIn('state_id', [\App\Models\Status::COMPLETED, \App\Models\Status::CANCELLED])
            ->exists();

        if ($hasActiveAppointments) {
            return response()->json([
                'message' => 'Bu personele ait aktif randevular bulunduğu için silinemez.',
            ], 409);
        }

        $staff_member->delete();
        return response()->json(['message' => 'Personel silindi']);
    }

    public function byCategory(Category $category, Request $request)
    {
        $perPage = max(1, min(100, (int) $request->get('per_page', 50)));

        $query = Staff::where('catagory_id', $category->id)
            ->with('person');

        $allowedSorts = ['id', 'job_title', 'email', 'created_at'];
        $sortBy = in_array($request->get('sort_by', 'id'), $allowedSorts, true) ? $request->get('sort_by', 'id') : 'id';
        $sortOrder = in_array(strtolower($request->get('sort_order', 'asc')), ['asc', 'desc'], true) ? strtolower($request->get('sort_order', 'asc')) : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return response()->json($query->paginate($perPage));
    }
}
