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
     * Listeleme + filtreleme + arama
     */
    /**
     * Login olmuş personel/admin'in rolüne göre randevuları döndürür:
     * - Sıradan personel: sadece kendi randevuları
     * - Admin: sadece kendi yönettiği personellerin randevuları
     */
    public function index(Request $request)
    {
        $loggedInStaff = $request->user(); // Staff instance (auth:staff guard)

        $query = Appointment::with(['staff.person', 'customer.person', 'service', 'status']);

        // Bu staff bir admin mi kontrol et
        $adminProfile = $loggedInStaff->adminProfile;

        if ($adminProfile) {
            // ADMIN: sadece kendi yönettiği personellerin randevularını gör
            $managedStaffIds = Staff::where('admin_id', $adminProfile->id)->pluck('id');
            $query->whereIn('staff_id', $managedStaffIds);
        } else {
            // SIRADAN PERSONEL: sadece kendi randevularını gör
            $query->where('staff_id', $loggedInStaff->id);
        }

        // Opsiyonel filtreler (her iki rol için de çalışır)
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
     * Yeni randevu oluşturma
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'start_date' => 'required|date|after:now',
            // customer_id artık validate edilmiyor, request'ten alınmıyor
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = $startDate->copy()->addMinutes($service->duration);

        $conflict = Appointment::conflicting($validated['staff_id'], $startDate, $endDate)->exists();

        if ($conflict) {
            return response()->json(['message' => 'Bu saat aralığında personelin başka bir randevusu var.'], 409);
        }

        $appointment = Appointment::create([
            'staff_id' => $validated['staff_id'],
            'customer_id' => $request->user()->id, // ← Token'dan gelen gerçek kullanıcı
            'service_id' => $validated['service_id'],
            'state_id' => Status::PENDING,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']), 201);
    }

    public function show(Request $request, Appointment $appointment)
    {
        $loggedInStaff = $request->user();
        $adminProfile = $loggedInStaff->adminProfile;

        if ($adminProfile) {
            $managedStaffIds = Staff::where('admin_id', $adminProfile->id)->pluck('id');
            if (!$managedStaffIds->contains($appointment->staff_id)) {
                return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
            }
        } else {
            if ($appointment->staff_id !== $loggedInStaff->id) {
                return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
            }
        }

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * Randevu düzenleme
     */
    public function update(Request $request, Appointment $appointment)
    {
        $loggedInStaff = $request->user();
        $adminProfile = $loggedInStaff->adminProfile;

        if ($adminProfile) {
            $managedStaffIds = Staff::where('admin_id', $adminProfile->id)->pluck('id');
            if (!$managedStaffIds->contains($appointment->staff_id)) {
                return response()->json(['message' => 'Bu randevuyu düzenleme yetkiniz yok'], 403);
            }
        } else {
            if ($appointment->staff_id !== $loggedInStaff->id) {
                return response()->json(['message' => 'Bu randevuyu düzenleme yetkiniz yok'], 403);
            }
        }

        $validated = $request->validate([
            'staff_id' => 'sometimes|exists:staff,id',
            'service_id' => 'sometimes|exists:services,id',
            'start_date' => 'sometimes|date|after:now',
        ]);

        $staffId = $validated['staff_id'] ?? $appointment->staff_id;
        $serviceId = $validated['service_id'] ?? $appointment->service_id;
        $service = Service::findOrFail($serviceId);

        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : $appointment->start_date;
        $endDate = $startDate->copy()->addMinutes($service->duration);

        // Çakışma kontrolü — kendi kaydını hariç tutarak
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

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * Randevu iptali (soft — silmiyoruz, durumunu değiştiriyoruz)
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        // Eğer müşteri olarak giriş yapılmışsa, sadece kendi randevusunu iptal edebilir
        if ($request->user() instanceof \App\Models\Customer && $appointment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Bu randevuyu iptal etme yetkiniz yok'], 403);
        }

        $appointment->update(['state_id' => Status::CANCELLED]);

        return response()->json([
            'message' => 'Randevu iptal edildi',
            'appointment' => $appointment->load('status'),
        ]);
    }

    /**
     * Gerçek silme (nadiren kullanılır, genelde cancel tercih edilir)
     */
    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return response()->json(['message' => 'Randevu silindi']);
    }

    // AppointmentController.php içine ekleyin

    /**
     * Login olmuş müşterinin SADECE kendi randevularını döndürür
     */
}
