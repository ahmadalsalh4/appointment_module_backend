<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Staff;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with('category');

        if ($request->filled('catagory_id')) {
            $query->where('catagory_id', $request->catagory_id);
        }

        $allowedSorts = ['id', 'name', 'duration', 'catagory_id', 'created_at'];
        $sortBy = in_array($request->get('sort_by', 'name'), $allowedSorts, true) ? $request->get('sort_by', 'name') : 'name';
        $sortOrder = in_array(strtolower($request->get('sort_order', 'asc')), ['asc', 'desc'], true) ? strtolower($request->get('sort_order', 'asc')) : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catagory_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100',
            'duration' => 'required|integer|min:5|max:240',
        ]);

        $service = Service::create($validated);

        return response()->json($service->load('category'), 201);
    }

    public function show(Service $service)
    {
        return response()->json($service->load('category'));
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'catagory_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:100',
            'duration' => 'sometimes|integer|min:5|max:240',
        ]);

        $service->update($validated);

        return response()->json($service->load('category'));
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json(['message' => 'Hizmet silindi']);
    }
    public function getAvailableStaff(Service $service, Request $request)
    {
        $perPage = max(1, min(100, (int) $request->get('per_page', 50)));

        $query = Staff::where('catagory_id', $service->catagory_id)
            ->with('person');

        $allowedSorts = ['id', 'job_title', 'email', 'created_at'];
        $sortBy = in_array($request->get('sort_by', 'id'), $allowedSorts, true) ? $request->get('sort_by', 'id') : 'id';
        $sortOrder = in_array(strtolower($request->get('sort_order', 'asc')), ['asc', 'desc'], true) ? strtolower($request->get('sort_order', 'asc')) : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return response()->json($query->paginate($perPage));
    }
}
