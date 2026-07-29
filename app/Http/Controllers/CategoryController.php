<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Category;
use App\Models\Status;
use App\Support\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query();

        if ($request->filled('name')) {
            $query->whereRaw('name LIKE ? '.SearchHelper::ESCAPE_CLAUSE, [SearchHelper::likeContains($request->name)]);
        }

        $sortBy = in_array(strtolower($request->get('sort_by', 'name')), ['id', 'name', 'created_at'], true) ? strtolower($request->get('sort_by', 'name')) : 'name';
        $sortOrder = in_array(strtolower($request->get('sort_order', 'asc')), ['asc', 'desc'], true) ? strtolower($request->get('sort_order', 'asc')) : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')],
        ]);

        $category = Category::create($validated);

        return response()->json($category->load('services'), 201);
    }

    public function show(Category $category)
    {
        // Public endpoint: do not expose soft-deleted records.
        if ($category->trashed()) {
            return response()->json(['message' => 'Kategori bulunamadı.'], 404);
        }

        return response()->json($category->load('services'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        return DB::transaction(function () use ($category) {
            // Lock the category row so a concurrent booking can't slip in
            // a new non-terminal appointment while we're checking.
            $locked = Category::where('id', $category->id)->lockForUpdate()->first();

            $hasActiveAppointments = Appointment::whereHas('service', function ($q) use ($locked) {
                $q->where('category_id', $locked->id);
            })->whereNotIn('state_id', [Status::COMPLETED, Status::CANCELLED])->exists();

            if ($hasActiveAppointments) {
                return response()->json([
                    'message' => 'Bu kategoriye ait aktif randevular bulunduğu için silinemez.',
                ], 409);
            }

            $locked->delete(); // soft-delete now that SoftDeletes is in place

            return response()->json(['message' => 'Kategori silindi']);
        });
    }
}
