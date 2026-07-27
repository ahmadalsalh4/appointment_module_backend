<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Category;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $sortBy = in_array(strtolower($request->get('sort_by', 'name')), ['id', 'name', 'created_at'], true) ? strtolower($request->get('sort_by', 'name')) : 'name';
        $sortOrder = in_array(strtolower($request->get('sort_order', 'asc')), ['asc', 'desc'], true) ? strtolower($request->get('sort_order', 'asc')) : 'asc';

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));

        return response()->json(
            Category::orderBy($sortBy, $sortOrder)->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $category = Category::create($validated);

        return response()->json($category->load('services'), 201);
    }

    public function show(Category $category)
    {
        return response()->json($category->load('services'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        return DB::transaction(function () use ($category) {
            $hasActiveAppointments = Appointment::whereHas('service', function ($q) use ($category) {
                $q->where('catagory_id', $category->id);
            })->whereNotIn('state_id', [Status::COMPLETED, Status::CANCELLED])->exists();

            if ($hasActiveAppointments) {
                return response()->json([
                    'message' => 'Bu kategoriye ait aktif randevular bulunduğu için silinemez.',
                ], 409);
            }

            $category->delete();
            return response()->json(['message' => 'Kategori silindi']);
        });
    }
}
