<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Status;
use App\Support\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('name')) {
            $query->whereRaw('name LIKE ? '.SearchHelper::ESCAPE_CLAUSE, [SearchHelper::likeContains($request->name)]);
        }

        $allowedSorts = ['id', 'name', 'duration', 'category_id', 'created_at'];
        $sortBy = in_array($request->get('sort_by', 'name'), $allowedSorts, true) ? $request->get('sort_by', 'name') : 'name';
        $sortOrder = in_array(strtolower($request->get('sort_order', 'asc')), ['asc', 'desc'], true) ? strtolower($request->get('sort_order', 'asc')) : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('services', 'name')->where('category_id', $request->input('category_id')),
            ],
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
        $categoryId = $request->input('category_id', $service->category_id);

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('services', 'name')
                    ->where('category_id', $categoryId)
                    ->ignore($service->id),
            ],
            'duration' => 'sometimes|integer|min:5|max:240',
        ]);

        // Changing duration without recomputing end_date of existing
        // non-terminal appointments would let the old (short) end_date
        // coexist with a service that now claims to take longer. We
        // refuse the change unless the admin has explicitly accepted
        // the cascade via `force_duration_change=true`, and log an
        // audit row in `service_duration_history` for traceability.
        if (array_key_exists('duration', $validated) && (int) $validated['duration'] !== (int) $service->duration) {
            $hasActive = $service->appointments()
                ->whereNotIn('state_id', [\App\Models\Status::COMPLETED, \App\Models\Status::CANCELLED])
                ->exists();

            if ($hasActive && ! $request->boolean('force_duration_change')) {
                return response()->json([
                    'message' => 'Bu hizmete ait aktif randevular bulunduğu için süre değiştirilemez. Yine de devam etmek için istekte `force_duration_change=true` gönderin ve mevcut randevuların end_date alanlarını manuel olarak güncelleyin.',
                ], 409);
            }
        }

        \DB::transaction(function () use ($service, $validated, $request) {
            $service->fill($validated);

            if ($request->boolean('force_duration_change')
                && array_key_exists('duration', $validated)
                && (int) $validated['duration'] !== (int) $service->getOriginal('duration')) {
                $oldDuration = (int) $service->getOriginal('duration');
                $newDuration = (int) $validated['duration'];
                $deltaMinutes = $newDuration - $oldDuration;

                // Stamp an audit row so the duration change is traceable.
                \DB::table('service_duration_history')->insert([
                    'service_id' => $service->id,
                    'old_duration' => $oldDuration,
                    'new_duration' => $newDuration,
                    'applied_at' => now(),
                ]);

                // Recompute end_date for every non-terminal appointment.
                $rows = $service->appointments()
                    ->whereNotIn('state_id', [\App\Models\Status::COMPLETED, \App\Models\Status::CANCELLED])
                    ->get(['id', 'start_date']);

                foreach ($rows as $row) {
                    $start = \Carbon\Carbon::parse($row->start_date);
                    \DB::table('appointments')
                        ->where('id', $row->id)
                        ->update(['end_date' => $start->copy()->addMinutes($newDuration)]);
                }
            }

            $service->save();
        });

        return response()->json($service->fresh()->load('category'));
    }

    public function destroy(Service $service)
    {
        return DB::transaction(function () use ($service) {
            $locked = Service::where('id', $service->id)->lockForUpdate()->first();

            $hasActiveAppointments = Appointment::where('service_id', $locked->id)
                ->whereNotIn('state_id', [Status::COMPLETED, Status::CANCELLED])
                ->exists();

            if ($hasActiveAppointments) {
                return response()->json([
                    'message' => 'Bu hizmete ait aktif randevular bulunduğu için silinemez.',
                ], 409);
            }

            // Also block if a soft-deleted category is still the parent;
            // restoring the service would otherwise dangle.
            if ($locked->category()->onlyTrashed()->exists()) {
                return response()->json([
                    'message' => 'Bu hizmetin kategorisi silinmiş. Hizmeti silmek için önce kategoriyi geri yükleyin.',
                ], 409);
            }

            $locked->delete(); // soft-delete

            return response()->json(['message' => 'Hizmet silindi']);
        });
    }

    public function getAvailableStaff(Service $service, Request $request)
    {
        $query = Staff::where('category_id', $service->category_id)->with('person');

        $allowedSorts = ['id', 'job_title', 'email', 'created_at'];
        $sortBy = in_array($request->get('sort_by', 'id'), $allowedSorts, true) ? $request->get('sort_by', 'id') : 'id';
        $sortOrder = in_array(strtolower($request->get('sort_order', 'asc')), ['asc', 'desc'], true) ? strtolower($request->get('sort_order', 'asc')) : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return response()->json($query->get());
    }
}
