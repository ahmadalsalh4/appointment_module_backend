<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with('category');

        if ($request->has('catagory_id')) {
            $query->where('catagory_id', $request->catagory_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catagory_id' => 'required|exists:catagorys,id',
            'name' => 'required|string|max:100',
            'duration' => 'required|integer|min:5|max:480',
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
            'catagory_id' => 'sometimes|exists:catagorys,id',
            'name' => 'sometimes|string|max:100',
            'duration' => 'sometimes|integer|min:5|max:480',
        ]);

        $service->update($validated);

        return response()->json($service);
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json(['message' => 'Hizmet silindi']);
    }
}
