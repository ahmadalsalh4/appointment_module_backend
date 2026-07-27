<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Status;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AvailabilityController extends Controller
{
    public function check(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date|after_or_equal:today',
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $staff = Staff::findOrFail($validated['staff_id']);

        if ($staff->catagory_id !== $service->catagory_id) {
            return response()->json(['message' => 'Bu personel seçilen hizmeti sunmamaktadır.'], 422);
        }

        $duration = $service->duration;
        $date = $validated['date'];

        // O günkü dolu randevuları çek
        $booked = Appointment::forStaff($validated['staff_id'])
            ->onDate($date)
            ->where('state_id', '!=', Status::CANCELLED)
            ->get(['start_date', 'end_date']);

        $availableSlots = [];

        foreach (Staff::WORK_BLOCKS as $block) {
            $blockStart = Carbon::parse("$date {$block['start']}");
            $blockEnd = Carbon::parse("$date {$block['end']}");
            $slot = $blockStart->copy();

            if ($blockStart->isToday()) {
                $now = Carbon::now();
                if ($slot->lt($now)) {
                    $minutesSinceMidnight = $now->diffInMinutes($blockStart->copy()->startOfDay());
                    $roundedMinutes = ceil($minutesSinceMidnight / 15) * 15;
                    $slot = $blockStart->copy()->startOfDay()->addMinutes($roundedMinutes);
                }
            }

            while ($slot->copy()->addMinutes($duration)->lte($blockEnd)) {
                $slotEnd = $slot->copy()->addMinutes($duration);

                $hasConflict = $booked->contains(function ($appt) use ($slot, $slotEnd) {
                    return $slot->lt($appt->end_date) && $slotEnd->gt($appt->start_date);
                });

                if (!$hasConflict) {
                    $availableSlots[] = $slot->format('H:i');
                }

                $slot->addMinutes(15);
            }
        }

        return response()->json(['available_slots' => $availableSlots]);
    }
}
