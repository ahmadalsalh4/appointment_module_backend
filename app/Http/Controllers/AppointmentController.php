<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Status;
use App\Support\AppointmentStateMachine;
use App\Support\Concerns\AppointmentListFilters;
use App\Support\Concerns\HandlesUniqueViolation;
use App\Support\Exceptions\AppointmentCategoryMismatchException;
use App\Support\Exceptions\AppointmentConflictException;
use App\Support\Exceptions\AppointmentForbiddenException;
use App\Support\Exceptions\AppointmentOutOfHoursException;
use App\Support\Exceptions\AppointmentWrongStateException;
use App\Support\WorkingHoursChecker;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    use AppointmentListFilters;
    use HandlesUniqueViolation;

    /**
     * ADMIN: Giriş yapmış adminin yönettiği personellere ait randevuları döndürür
     */
    public function index(Request $request)
    {
        $validated = $this->validateListRequest($request, [
            'tab', 'status_id', 'staff_id', 'date', 'customer_name',
            'sort_by', 'sort_order', 'per_page', 'page',
        ]);

        $admin = $request->user();
        $managedStaffIds = Staff::where('admin_id', (int) $admin->id)->pluck('id');

        $query = Appointment::with(['staff.person', 'customer.person', 'service', 'status'])
            ->whereIn('staff_id', $managedStaffIds);

        if ($request->filled('staff_id')) {
            $query->forStaff((int) $request->staff_id);
        }
        if ($request->filled('customer_name')) {
            $query->searchCustomer((string) $request->customer_name);
        }

        $this->applyListFilters($query, $request, $validated);

        return response()->json($query->paginate($this->paginationSize($request)));
    }

    /**
     * CUSTOMER: Login olmuş müşterinin sadece kendi randevularını döndürür
     */
    public function myAppointments(Request $request)
    {
        // `staff_id` is intentionally NOT in the allowlist — the customer
        // already sees only their own appointments, so a staff filter
        // would be a no-op (kept here so we 422 instead of silently ignore).
        $validated = $this->validateListRequest($request, [
            'tab', 'status_id', 'date',
            'sort_by', 'sort_order', 'per_page', 'page',
        ]);

        $query = Appointment::where('customer_id', $request->user()->id)
            ->with(['staff.person', 'service', 'status']);

        $this->applyListFilters($query, $request, $validated);

        return response()->json($query->paginate($this->paginationSize($request)));
    }

    /**
     * STAFF: Sadece kendi randevularını döner
     */
    public function myStaffAppointments(Request $request)
    {
        $validated = $this->validateListRequest($request, [
            'tab', 'status_id', 'date', 'customer_name',
            'sort_by', 'sort_order', 'per_page', 'page',
        ]);

        $staff = $request->user();

        $query = Appointment::where('staff_id', $staff->id)
            ->with(['customer.person', 'service', 'status']);

        if ($request->filled('customer_name')) {
            $query->searchCustomer((string) $request->customer_name);
        }

        $this->applyListFilters($query, $request, $validated);

        return response()->json($query->paginate($this->paginationSize($request)));
    }

    /**
     * STAFF: kendi randevusunun durumunu günceller (örn. "tamamlandı" olarak işaretleme)
     * (route: auth:staff guard'ı altında — admin'in update()'inden ayrı,
     *  çünkü personel sadece KENDİ randevusunu, admin ise ekibinin TÜMÜNÜ değiştirebilir)
     */
    public function updateStatusAsStaff(Request $request, Appointment $appointment)
    {
        $staff = $request->user(); // auth:staff guard'ı sayesinde her zaman gerçek Staff instance

        $validated = $request->validate([
            'state_id' => 'required|integer|in:'.Status::CONFIRMED.','.Status::COMPLETED.','.Status::CANCELLED,
        ]);

        return DB::transaction(function () use ($appointment, $staff, $validated) {
            $locked = Appointment::where('id', $appointment->id)->lockForUpdate()->first();

            if (! $locked) {
                return response()->json(['message' => 'Randevu bulunamadı.'], 404);
            }

            if ((int) $locked->staff_id !== (int) $staff->id) {
                return response()->json(['message' => 'Bu randevuyu güncelleme yetkiniz yok'], 403);
            }

            $error = AppointmentStateMachine::errorMessage(
                (int) $locked->state_id,
                (int) $validated['state_id'],
                'staff',
            );
            if ($error !== null) {
                return response()->json(['message' => $error], 422);
            }

            $locked->update(['state_id' => $validated['state_id']]);

            return response()->json($locked->load(['staff.person', 'customer.person', 'service', 'status']));
        });
    }

    /**
     * CUSTOMER: kendi randevusunun detayı (sadece kendi randevusuysa erişebilir)
     */
    public function myAppointmentDetail(Request $request, Appointment $appointment)
    {
        if ((int) $appointment->customer_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
        }

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * STAFF: kendi randevusunun detayı (sadece kendi randevusuysa erişebilir)
     */
    public function myStaffAppointmentDetail(Request $request, Appointment $appointment)
    {
        if ((int) $appointment->staff_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
        }

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * CUSTOMER: yeni randevu oluşturma (kendi adına)
     */
    public function store(Request $request)
    {
        $tz = Staff::BUSINESS_TIMEZONE;

        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'start_date' => [
                'required',
                'date',
                // 'after:now' uses the app's default timezone. We re-validate
                // against the business timezone below so a UTC server doesn't
                // accept a 09:00 Istanbul slot that has already passed in
                // Istanbul time.
                'after:now',
                function ($attribute, $value, $fail) use ($tz) {
                    try {
                        $date = Carbon::parse($value, $tz);
                        if ($date->minute % 15 !== 0 || $date->second !== 0) {
                            $fail('Randevu başlangıç saati sadece :00, :15, :30 veya :45. dakikalara ayarlanabilir (Örn: 15:00:00, 15:15:00).');
                        }
                        if ($date->lt(Carbon::now($tz))) {
                            $fail('Randevu saati geçmişte kalamaz.');
                        }
                    } catch (\Exception $e) {
                        $fail('Geçersiz tarih formatı.');
                    }
                },
            ],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $staff = Staff::findOrFail($validated['staff_id']);

        if ((int) $staff->category_id !== (int) $service->category_id) {
            return response()->json([
                'message' => 'Bu personel seçilen hizmeti sunmamaktadır.',
            ], 422);
        }

        // Parse in the business timezone so the stored UTC-equivalent
        // matches what the user actually means in Europe/Istanbul.
        $startDate = Carbon::parse($validated['start_date'], $tz);
        $endDate = $startDate->copy()->addMinutes($service->duration);

        if (! WorkingHoursChecker::isWithin($startDate, $endDate, Staff::WORK_BLOCKS)) {
            return response()->json([
                'message' => 'Seçilen saat aralığı personelin mesai saatleri (09:00-12:00, 13:00-17:00) dışındadır.',
            ], 422);
        }

        try {
            $appointment = DB::transaction(function () use ($validated, $startDate, $endDate, $request) {
                $this->lockStaff((int) $validated['staff_id']);

                $lockedStaff = Staff::where('id', $validated['staff_id'])->lockForUpdate()->first();
                if (! $lockedStaff) {
                    return null;
                }

                $conflict = Appointment::conflicting($validated['staff_id'], $startDate, $endDate)->exists();

                if ($conflict) {
                    throw new AppointmentConflictException;
                }

                return Appointment::create([
                    'staff_id' => $validated['staff_id'],
                    'customer_id' => $request->user()->id,
                    'service_id' => $validated['service_id'],
                    'state_id' => Status::PENDING,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
            });
        } catch (QueryException $e) {
            // The DB-level UNIQUE(staff_id, start_date) caught a race we
            // missed. Treat it the same as the in-app conflict check.
            if ($this->isUniqueViolation($e)) {
                throw new AppointmentConflictException;
            }
            throw $e;
        }

        if ($appointment === null) {
            return response()->json(['message' => 'Personel bulunamadı.'], 404);
        }

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
        if (! $this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu görme yetkiniz yok'], 403);
        }

        return response()->json($appointment->load(['staff.person', 'customer.person', 'service', 'status']));
    }

    /**
     * ADMIN: randevu güncelleme (yetki kontrolüyle)
     */
    public function update(Request $request, Appointment $appointment)
    {
        if (! $this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu düzenleme yetkiniz yok'], 403);
        }

        $tz = Staff::BUSINESS_TIMEZONE;

        $validated = $request->validate([
            'state_id' => 'sometimes|exists:statuses,id',
            'staff_id' => 'sometimes|exists:staff,id',
            'service_id' => 'sometimes|exists:services,id',
            'start_date' => [
                'sometimes',
                'date',
                'after:now',
                function ($attribute, $value, $fail) use ($tz) {
                    try {
                        $date = Carbon::parse($value, $tz);
                        if ($date->minute % 15 !== 0 || $date->second !== 0) {
                            $fail('Randevu başlangıç saati sadece :00, :15, :30 veya :45. dakikalara ayarlanabilir (Örn: 15:00:00, 15:15:00).');
                        }
                        if ($date->lt(Carbon::now($tz))) {
                            $fail('Randevu saati geçmişte kalamaz.');
                        }
                    } catch (\Exception $e) {
                        $fail('Geçersiz tarih formatı.');
                    }
                },
            ],
        ]);

        $staffId = $validated['staff_id'] ?? $appointment->staff_id;
        $serviceId = $validated['service_id'] ?? $appointment->service_id;

        try {
            $result = DB::transaction(function () use ($request, $appointment, $validated, $staffId, $serviceId) {
                $this->lockStaff((int) $staffId);

                $locked = Appointment::where('id', $appointment->id)->lockForUpdate()->first();
                if (! $locked) {
                    return ['status' => 404, 'body' => ['message' => 'Randevu bulunamadı.']];
                }

                // State guard: don't allow edits on terminal states.
                if (in_array($locked->state_id, [Status::COMPLETED, Status::CANCELLED], true)) {
                    return ['status' => 422, 'body' => ['message' => 'Tamamlanmış veya iptal edilmiş randevular düzenlenemez.']];
                }

                $updateData = [];

                if (isset($validated['state_id'])) {
                    $error = AppointmentStateMachine::errorMessage(
                        (int) $locked->state_id,
                        (int) $validated['state_id'],
                        'admin',
                    );
                    if ($error !== null) {
                        return ['status' => 422, 'body' => ['message' => $error]];
                    }
                    $updateData['state_id'] = $validated['state_id'];
                }

                if (isset($validated['staff_id'])) {
                    $isManaged = Staff::where('admin_id', (int) $request->user()->id)
                        ->where('id', $staffId)->exists();
                    if (! $isManaged) {
                        return ['status' => 403, 'body' => ['message' => 'Bu personel sizin yönetiminizde değil.']];
                    }
                }

                $needsConflictCheck = isset($validated['staff_id']) || isset($validated['service_id']) || isset($validated['start_date']);

                if ($needsConflictCheck) {
                    $service = Service::findOrFail($serviceId);
                    $lockedStaff = Staff::where('id', $staffId)->lockForUpdate()->first();
                    if (! $lockedStaff) {
                        return ['status' => 404, 'body' => ['message' => 'Personel bulunamadı.']];
                    }

                    if ((int) $lockedStaff->category_id !== (int) $service->category_id) {
                        return ['status' => 422, 'body' => ['message' => 'Bu personel seçilen hizmeti sunmamaktadır.']];
                    }

                    $tz = Staff::BUSINESS_TIMEZONE;
                    $startDate = isset($validated['start_date'])
                        ? Carbon::parse($validated['start_date'], $tz)
                        : $locked->start_date;

                    $endDate = $startDate->copy()->addMinutes($service->duration);

                    if (! WorkingHoursChecker::isWithin($startDate, $endDate, Staff::WORK_BLOCKS)) {
                        return ['status' => 422, 'body' => ['message' => 'Seçilen saat aralığı personelin mesai saatleri (09:00-12:00, 13:00-17:00) dışındadır.']];
                    }

                    $conflict = Appointment::conflicting($staffId, $startDate, $endDate, $locked->id)->exists();

                    if ($conflict) {
                        return ['status' => 409, 'body' => ['message' => 'Bu saat aralığında personelin başka bir randevusu var.']];
                    }

                    $updateData['staff_id'] = $staffId;
                    $updateData['service_id'] = $serviceId;
                    $updateData['start_date'] = $startDate;
                    $updateData['end_date'] = $endDate;
                }

                $locked->update($updateData);

                return ['status' => 200, 'body' => $locked->load(['staff.person', 'customer.person', 'service', 'status'])];
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return response()->json([
                    'message' => 'Bu saat aralığında personelin başka bir randevusu var.',
                ], 409);
            }
            throw $e;
        }

        return response()->json($result['body'], $result['status']);
    }

    /**
     * CUSTOMER: randevu iptali (sadece kendi randevusu)
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        return DB::transaction(function () use ($appointment, $request) {
            $locked = Appointment::where('id', $appointment->id)->lockForUpdate()->first();

            if (! $locked) {
                return response()->json(['message' => 'Randevu bulunamadı.'], 404);
            }

            if ((int) $locked->customer_id !== (int) $request->user()->id) {
                return response()->json(['message' => 'Bu randevuyu iptal etme yetkiniz yok'], 403);
            }

            if (in_array((int) $locked->state_id, [Status::COMPLETED, Status::CANCELLED], true)) {
                return response()->json(['message' => 'Tamamlanmış veya zaten iptal edilmiş randevular tekrar iptal edilemez.'], 422);
            }

            $locked->update(['state_id' => Status::CANCELLED]);

            return response()->json($locked->load(['staff.person', 'customer.person', 'service', 'status']));
        });
    }

    /**
     * CUSTOMER: randevu düzenleme (sadece PENDING durumundaki kendi randevusu)
     */
    public function updateMyAppointment(Request $request, Appointment $appointment)
    {
        $tz = Staff::BUSINESS_TIMEZONE;

        $validated = $request->validate([
            'staff_id' => 'sometimes|exists:staff,id',
            'service_id' => 'sometimes|exists:services,id',
            'start_date' => [
                'sometimes',
                'date',
                'after:now',
                function ($attribute, $value, $fail) use ($tz) {
                    try {
                        $date = Carbon::parse($value, $tz);
                        if ($date->minute % 15 !== 0 || $date->second !== 0) {
                            $fail('Randevu başlangıç saati sadece :00, :15, :30 veya :45. dakikalara ayarlanabilir (Örn: 15:00:00, 15:15:00).');
                        }
                        if ($date->lt(Carbon::now($tz))) {
                            $fail('Randevu saati geçmişte kalamaz.');
                        }
                    } catch (\Exception $e) {
                        $fail('Geçersiz tarih formatı.');
                    }
                },
            ],
        ]);

        $staffId = $validated['staff_id'] ?? $appointment->staff_id;
        $serviceId = $validated['service_id'] ?? $appointment->service_id;

        try {
            $appointment = DB::transaction(function () use ($request, $appointment, $validated, $staffId, $serviceId) {
                $this->lockStaff((int) $staffId);

                $locked = Appointment::where('id', $appointment->id)->lockForUpdate()->first();
                if (! $locked) {
                    return null;
                }

                if ((int) $locked->customer_id !== (int) $request->user()->id) {
                    throw new AppointmentForbiddenException;
                }

                if ($locked->state_id !== Status::PENDING) {
                    throw new AppointmentWrongStateException;
                }

                $service = Service::findOrFail($serviceId);
                $lockedStaff = Staff::where('id', $staffId)->lockForUpdate()->first();
                if (! $lockedStaff) {
                    return null;
                }

                if ((int) $lockedStaff->category_id !== (int) $service->category_id) {
                    throw new AppointmentCategoryMismatchException;
                }

                $tz = Staff::BUSINESS_TIMEZONE;
                $startDate = isset($validated['start_date'])
                    ? Carbon::parse($validated['start_date'], $tz)
                    : $locked->start_date;

                $endDate = $startDate->copy()->addMinutes($service->duration);

                if (! WorkingHoursChecker::isWithin($startDate, $endDate, Staff::WORK_BLOCKS)) {
                    throw new AppointmentOutOfHoursException;
                }

                $conflict = Appointment::conflicting($staffId, $startDate, $endDate, $locked->id)->exists();

                if ($conflict) {
                    throw new AppointmentConflictException;
                }

                $locked->update([
                    'staff_id' => $staffId,
                    'service_id' => $serviceId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);

                return $locked;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new AppointmentConflictException;
            }
            throw $e;
        }

        if ($appointment === null) {
            return response()->json(['message' => 'Randevu bulunamadı.'], 404);
        }

        return response()->json(
            $appointment->load(['staff.person', 'customer.person', 'service', 'status'])
        );
    }

    /**
     * ADMIN: randevu silme (yetki kontrolüyle)
     */
    public function destroy(Request $request, Appointment $appointment)
    {
        if (! $this->canAccess($request->user(), $appointment)) {
            return response()->json(['message' => 'Bu randevuyu silme yetkiniz yok'], 403);
        }

        DB::transaction(function () use ($appointment) {
            $appointment->delete();
        });

        return response()->json(['message' => 'Randevu silindi']);
    }

    /**
     * Yardımcı: login olmuş Admin, bu randevuya erişebilir mi?
     * (show/update/destroy sadece auth:admin altında olduğu için
     *  $admin her zaman gerçek bir Admin instance'ıdır)
     */
    private function canAccess(Admin $admin, Appointment $appointment): bool
    {
        return Staff::where('admin_id', (int) $admin->id)
            ->where('id', (int) $appointment->staff_id)
            ->exists();
    }

    /**
     * Acquire a per-staff lock that works on every supported driver.
     * PostgreSQL gets the strongest guarantee via advisory locks; other
     * drivers rely on lockForUpdate() inside the transaction plus the
     * UNIQUE(staff_id, start_date) DB constraint.
     */
    private function lockStaff(int $staffId): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$staffId]);
        }
    }
}
