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
     * ADMIN: sadece kendi yönettiği personellerin randevularını döner
     * (route: auth:admin guard'ı altında)
     */
    public function index(Request $request)
    {
        $admin = $request->user(); // auth:admin guard'ı sayesinde her zaman gerçek Admin instance

        $managedStaffIds = Staff::where('admin_id', $admin->id)->pluck('id');

        $query = Appointment::with(['staff.person', 'customer.person', 'service', 'status'])
            ->whereIn('staff_id', $managedStaffIds);

        if ($request->filled('status_id')) {
            $query->byStatus($request->status_id);
        }

        if ($request->filled('date')) {
            $query->onDate($request->date);
        }

        if ($request->filled('customer_name')) {
            $query->searchCustomer($request->customer_name);
        }

        return response()->json($query->orderBy('start_date')->get());
    }

    /**
     * CUSTOMER: login olmuş müşterinin sadece kendi randevularını döndürür
     */
    public function myAppointments(Request $request)
    {
        $appointments = Appointment::where('customer_id', $request->user()->id)
            ->with(['staff.person', 'service', 'status'])
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json($appointments);
    }

    /**
     * STAFF: sadece kendi randevularını döner
     * (route: auth:staff guard'ı altında)
     */
    public function myStaffAppointments(Request $request)
    {
        $staff = $request->user(); // auth:staff guard'ı sayesinde her zaman gerçek Staff instance

        $query = Appointment::where('staff_id', $staff->id)
            ->with(['customer.person', 'service', 'status']);

        if ($request->filled('status_id')) {
            $query->byStatus($request->status_id);
        }

        if ($request->filled('date')) {
            $query->onDate($request->date);
        }

        return response()->json($query->orderBy('start_date')->get());
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
            'state_id' => 'required|exists:statuses,id',
        ]);

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
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = $startDate->copy()->addMinutes($service->duration);

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
     * ADMIN: randevu durumunu güncelleme (yetki kontrolüyle)
     * Not: sadece state_id değiştirilebilir (onaylama/tamamlama gibi).
     * Personel/müşteri/tarih değişikliği için ayrı bir "reschedule" akışı düşünülebilir.
     */
    public function update(Request $request, Appointment $appointment)
    {
        if (!$this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu düzenleme yetkiniz yok'], 403);
        }

        $validated = $request->validate([
            'state_id' => 'required|exists:statuses,id',
        ]);

        $appointment->update([
            'state_id' => $validated['state_id'],
        ]);

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

        $appointment->update(['state_id' => Status::CANCELLED]);

        return response()->json([
            'message' => 'Randevu iptal edildi',
            'appointment' => $appointment->load('status'),
        ]);
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
