<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Status;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * ADMIN: Giriş yapmış adminin yönettiği personellere ait randevuları döndürür
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        $managedStaffIds = Staff::where('admin_id', $admin->id)->pluck('id');

        $query = Appointment::with(['staff.person', 'customer.person', 'service', 'status'])
            ->whereIn('staff_id', $managedStaffIds);

        if ($request->filled('tab')) {
            $query->tab($request->tab);
        }

        if ($request->filled('status_id')) {
            $query->byStatus($request->status_id);
        }

        if ($request->filled('date')) {
            $query->onDate($request->date);
        }

        if ($request->filled('staff_id')) {
            $query->forStaff($request->staff_id);
        }

        if ($request->filled('customer_name')) {
            $query->searchCustomer($request->customer_name);
        }

        $allowedSorts = ['start_date', 'state_id', 'created_at'];
        if ($request->filled('sort_by') && in_array($request->sort_by, $allowedSorts)) {
            $query->orderBy($request->sort_by, $request->get('sort_order', 'asc'));
        } else {
            $query->orderBy('start_date', 'asc');
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    /**
     * CUSTOMER: Login olmuş müşterinin sadece kendi randevularını döndürür
     */
    public function myAppointments(Request $request)
    {
        $query = Appointment::where('customer_id', $request->user()->id)
            ->with(['staff.person', 'service', 'status']);

        if ($request->filled('tab')) {
            $query->tab($request->tab);
        }

        if ($request->filled('status_id')) {
            $query->byStatus($request->status_id);
        }

        if ($request->filled('staff_id')) {
            $query->forStaff($request->staff_id);
        }

        if ($request->filled('date')) {
            $query->onDate($request->date);
        }

        $allowedSorts = ['start_date', 'state_id', 'created_at'];
        if ($request->filled('sort_by') && in_array($request->sort_by, $allowedSorts)) {
            $query->orderBy($request->sort_by, $request->get('sort_order', 'asc'));
        } else {
            $query->orderBy('start_date', 'asc');
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    /**
     * STAFF: Sadece kendi randevularını döner
     */
    public function myStaffAppointments(Request $request)
    {
        $staff = $request->user();

        $query = Appointment::where('staff_id', $staff->id)
            ->with(['customer.person', 'service', 'status']);

        if ($request->filled('tab')) {
            $query->tab($request->tab);
        }

        if ($request->filled('status_id')) {
            $query->byStatus($request->status_id);
        }

        if ($request->filled('date')) {
            $query->onDate($request->date);
        }

        if ($request->filled('customer_name')) {
            $query->searchCustomer($request->customer_name);
        }

        $allowedSorts = ['start_date', 'state_id', 'created_at'];
        if ($request->filled('sort_by') && in_array($request->sort_by, $allowedSorts)) {
            $query->orderBy($request->sort_by, $request->get('sort_order', 'asc'));
        } else {
            $query->orderBy('start_date', 'asc');
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    /**
     * STAFF: kendi randevusunun durumunu günceller (örn. "tamamlandı" olarak işaretleme)
     * (route: auth:staff guard'ı altında — admin'in update()'inden ayrı,
     *  çünkü personel sadece KENDİ randevusunu, admin ise ekibinin TÜMÜNÜ değiştirebilir)
     */
    public function updateStatusAsStaff(Request $request, Appointment $appointment)
    {
        $staff = $request->user(); // auth:staff guard'ı sayesinde her zaman gerçek Staff instance

        if ($appointment->staff_id !== $staff->id) {
            return response()->json(['message' => 'Bu randevuyu güncelleme yetkiniz yok'], 403);
        }

        $validated = $request->validate([
            'state_id' => 'required|in:' . Status::CONFIRMED . ',' . Status::COMPLETED . ',' . Status::CANCELLED,
        ]);

        if ($validated['state_id'] == Status::CANCELLED && in_array($appointment->state_id, [Status::COMPLETED, Status::CANCELLED])) {
            return response()->json(['message' => 'Tamamlanmış veya zaten iptal edilmiş randevular tekrar iptal edilemez.'], 422);
        }

        $appointment->update([
            'state_id' => $validated['state_id'],
        ]);

        return response()->json($appointment->load(['customer.person', 'service', 'status']));
    }

    /**
     * CUSTOMER: kendi randevusunun detayı (sadece kendi randevusuysa erişebilir)
     */
    public function myAppointmentDetail(Request $request, Appointment $appointment)
    {
        if ($appointment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
        }

        return response()->json($appointment->load(['staff.person', 'service', 'status']));
    }

    /**
     * STAFF: kendi randevusunun detayı (sadece kendi randevusuysa erişebilir)
     */
    public function myStaffAppointmentDetail(Request $request, Appointment $appointment)
    {
        if ($appointment->staff_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
        }

        return response()->json($appointment->load(['customer.person', 'service', 'status']));
    }

    /**
     * CUSTOMER: yeni randevu oluşturma (kendi adına)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'start_date' => [
                'required',
                'date',
                'after:now',
                function ($attribute, $value, $fail) {
                    try {
                        $date = Carbon::parse($value);
                        // Dakika 00, 15, 30 veya 45 olmalı ve saniye 0 olmalı
                        if ($date->minute % 15 !== 0 || $date->second !== 0) {
                            $fail('Randevu başlangıç saati sadece :00, :15, :30 veya :45. dakikalara ayarlanabilir (Örn: 15:00:00, 15:15:00).');
                        }
                    } catch (\Exception $e) {
                        $fail('Geçersiz tarih formatı.');
                    }
                },
            ],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $staff = Staff::findOrFail($validated['staff_id']);

        if ($staff->catagory_id !== $service->catagory_id) {
            return response()->json([
                'message' => 'Bu personel seçilen hizmeti sunmamaktadır.',
            ], 422);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = $startDate->copy()->addMinutes($service->duration);

        if (!$this->isWithinWorkingHours($startDate, $endDate)) {
            return response()->json([
                'message' => 'Seçilen saat aralığı personelin mesai saatleri (09:00-12:00, 13:00-17:00) dışındadır.',
            ], 422);
        }

        $conflict = Appointment::conflicting($validated['staff_id'], $startDate, $endDate)->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'Bu saat aralığında personelin başka bir randevusu var.',
            ], 409);
        }

        $appointment = Appointment::create([
            'staff_id' => $validated['staff_id'],
            'customer_id' => $request->user()->id,
            'service_id' => $validated['service_id'],
            'state_id' => Status::PENDING,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return response()->json(
            $appointment->load(['staff.person', 'customer.person', 'service', 'status']),
            201
        );
    }

    /**
     * ADMIN: tek randevu detayı (yetki kontrolüyle)
     */
    public function show(Request $request, Appointment $appointment)
    {
        if (!$this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
        }

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * ADMIN: randevu güncelleme (yetki kontrolüyle)
     */
    public function update(Request $request, Appointment $appointment)
    {
        if (!$this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu düzenleme yetkiniz yok'], 403);
        }

        $validated = $request->validate([
            'state_id' => 'sometimes|exists:statuses,id',
            'staff_id' => 'sometimes|exists:staff,id',
            'service_id' => 'sometimes|exists:services,id',
            'start_date' => [
                'sometimes',
                'date',
                function ($attribute, $value, $fail) {
                    try {
                        $date = Carbon::parse($value);
                        if ($date->minute % 15 !== 0 || $date->second !== 0) {
                            $fail('Randevu başlangıç saati sadece :00, :15, :30 veya :45. dakikalara ayarlanabilir (Örn: 15:00:00, 15:15:00).');
                        }
                    } catch (\Exception $e) {
                        $fail('Geçersiz tarih formatı.');
                    }
                },
            ],
        ]);

        $staffId = $validated['staff_id'] ?? $appointment->staff_id;
        $serviceId = $validated['service_id'] ?? $appointment->service_id;

        $updateData = [];
        if (isset($validated['state_id'])) {
            $updateData['state_id'] = $validated['state_id'];
        }

        $needsConflictCheck = isset($validated['staff_id']) || isset($validated['service_id']) || isset($validated['start_date']);

        if ($needsConflictCheck) {
            $service = Service::findOrFail($serviceId);
            $staff = Staff::findOrFail($staffId);

            if ($staff->catagory_id !== $service->catagory_id) {
                return response()->json([
                    'message' => 'Bu personel seçilen hizmeti sunmamaktadır.',
                ], 422);
            }

            $startDate = isset($validated['start_date'])
                ? Carbon::parse($validated['start_date'])
                : $appointment->start_date;

            $endDate = $startDate->copy()->addMinutes($service->duration);

            if (!$this->isWithinWorkingHours($startDate, $endDate)) {
                return response()->json([
                    'message' => 'Seçilen saat aralığı personelin mesai saatleri (09:00-12:00, 13:00-17:00) dışındadır.',
                ], 422);
            }

            $conflict = Appointment::conflicting($staffId, $startDate, $endDate, $appointment->id)->exists();

            if ($conflict) {
                return response()->json([
                    'message' => 'Bu saat aralığında personelin başka bir randevusu var.',
                ], 409);
            }

            $updateData['staff_id'] = $staffId;
            $updateData['service_id'] = $serviceId;
            $updateData['start_date'] = $startDate;
            $updateData['end_date'] = $endDate;
        }

        $appointment->update($updateData);

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * CUSTOMER: randevu iptali (sadece kendi randevusu)
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        if ($appointment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu randevuyu iptal etme yetkiniz yok'], 403);
        }

        if (in_array($appointment->state_id, [Status::COMPLETED, Status::CANCELLED])) {
            return response()->json(['message' => 'Tamamlanmış veya zaten iptal edilmiş randevular tekrar iptal edilemez.'], 422);
        }

        $appointment->update(['state_id' => Status::CANCELLED]);

        return response()->json([
            'message' => 'Randevu iptal edildi',
            'appointment' => $appointment->load('status'),
        ]);
    }

    /**
     * CUSTOMER: randevu düzenleme (sadece PENDING durumundaki kendi randevusu)
     */
    public function updateMyAppointment(Request $request, Appointment $appointment)
    {
        if ($appointment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu randevuyu düzenleme yetkiniz yok'], 403);
        }

        if ($appointment->state_id !== Status::PENDING) {
            return response()->json(['message' => 'Sadece onay bekleyen randevular düzenlenebilir.'], 422);
        }

        $validated = $request->validate([
            'staff_id' => 'sometimes|exists:staff,id',
            'service_id' => 'sometimes|exists:services,id',
            'start_date' => [
                'sometimes',
                'date',
                'after:now',
                function ($attribute, $value, $fail) {
                    try {
                        $date = Carbon::parse($value);
                        if ($date->minute % 15 !== 0 || $date->second !== 0) {
                            $fail('Randevu başlangıç saati sadece :00, :15, :30 veya :45. dakikalara ayarlanabilir (Örn: 15:00:00, 15:15:00).');
                        }
                    } catch (\Exception $e) {
                        $fail('Geçersiz tarih formatı.');
                    }
                },
            ],
        ]);

        $staffId = $validated['staff_id'] ?? $appointment->staff_id;
        $serviceId = $validated['service_id'] ?? $appointment->service_id;

        $service = Service::findOrFail($serviceId);
        $staff = Staff::findOrFail($staffId);

        if ($staff->catagory_id !== $service->catagory_id) {
            return response()->json([
                'message' => 'Bu personel seçilen hizmeti sunmamaktadır.',
            ], 422);
        }

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : $appointment->start_date;

        $endDate = $startDate->copy()->addMinutes($service->duration);

        if (!$this->isWithinWorkingHours($startDate, $endDate)) {
            return response()->json([
                'message' => 'Seçilen saat aralığı personelin mesai saatleri (09:00-12:00, 13:00-17:00) dışındadır.',
            ], 422);
        }

        $conflict = Appointment::conflicting($staffId, $startDate, $endDate, $appointment->id)->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'Bu saat aralığında personelin başka bir randevusu var.',
            ], 409);
        }

        $appointment->update([
            'staff_id' => $staffId,
            'service_id' => $serviceId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return response()->json(
            $appointment->load(['staff.person', 'customer.person', 'service', 'status'])
        );
    }

    /**
     * ADMIN: randevu silme (yetki kontrolüyle)
     */
    public function destroy(Request $request, Appointment $appointment)
    {
        if (!$this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu silme yetkiniz yok'], 403);
        }

        $appointment->delete();

        return response()->json(['message' => 'Randevu silindi']);
    }

    /**
     * Yardımcı: Randevunun personelin çalışma saatleri içinde olup olmadığını kontrol eder
     */
    private function isWithinWorkingHours(Carbon $startDate, Carbon $endDate): bool
    {
        if ($startDate->toDateString() !== $endDate->toDateString()) {
            return false;
        }

        $dateStr = $startDate->toDateString();

        foreach (Staff::WORK_BLOCKS as $block) {
            $blockStart = Carbon::parse("{$dateStr} {$block['start']}");
            $blockEnd = Carbon::parse("{$dateStr} {$block['end']}");

            if ($startDate->gte($blockStart) && $endDate->lte($blockEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Yardımcı: login olmuş Admin, bu randevuya erişebilir mi?
     * (show/update/destroy sadece auth:admin altında olduğu için
     *  $admin her zaman gerçek bir Admin instance'ıdır)
     */
    private function canAccess(Admin $admin, Appointment $appointment): bool
    {
        $managedStaffIds = Staff::where('admin_id', $admin->id)->pluck('id');
        return $managedStaffIds->contains($appointment->staff_id);
    }
}
