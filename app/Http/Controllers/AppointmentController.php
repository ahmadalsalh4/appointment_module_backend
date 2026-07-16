<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Status;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Login olmuş personel/admin'in rolüne göre randevuları döndürür
     */
    public function index(Request $request)
    {
        $query = Appointment::with(['staff.person', 'customer.person', 'service', 'status']);

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
     * Login olmuş müşterinin sadece kendi randevularını döndürür
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
     * Yeni randevu oluşturma (müşteri kendi adına)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'start_date' => 'required|date|after:now',
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

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']), 201);
    }

    /**
     * Tek randevu detayı (yetki kontrolüyle)
     */
    public function show(Request $request, Appointment $appointment)
    {
        if (!$this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
        }

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * Randevu düzenleme (yetki kontrolüyle)
     */
    public function update(Request $request, Appointment $appointment)
    {
        // 1. Yetki kontrolü (Aynı kalsın)
        if (!$this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu düzenleme yetkiniz yok'], 403);
        }

        // 2. SADECE state_id alanının güncellenmesine izin ver
        $validated = $request->validate([
            'state_id' => 'required|exists:statuses,id', // Sadece durum değiştirilebilir
        ]);

        // (Opsiyonel) İptal edilmiş veya tamamlanmış bir randevunun durumu tekrar değiştirilemesin isterseniz:
        /*
    if ($appointment->state_id == Status::CANCELLED || $appointment->state_id == Status::COMPLETED) {
        return response()->json(['message' => 'Bu randevunun durumu artık değiştirilemez.'], 422);
    }
    */

        // 3. Sadece state alanını güncelle
        $appointment->update([
            'state_id' => $validated['state_id'],
        ]);

        // 4. Güncellenmiş randevuyu ilişkileriyle birlikte döndür
        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * Randevu iptali (müşteri, sadece kendi randevusu)
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
     * Randevu silme (personel/admin, yetki kontrolüyle)
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
     * Yardımcı: login olmuş personel/admin bu randevuya erişebilir mi?
     */
    private function canAccess(Staff $loggedInStaff, Appointment $appointment): bool
    {
        $adminProfile = $loggedInStaff->adminProfile;

        if ($adminProfile) {
            $managedStaffIds = Staff::where('admin_id', $adminProfile->id)->pluck('id');
            return $managedStaffIds->contains($appointment->staff_id);
        }

        return $appointment->staff_id === $loggedInStaff->id;
    }
}
