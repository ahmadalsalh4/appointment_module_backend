<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        $tz = Staff::BUSINESS_TIMEZONE;

        $dayStart = Carbon::parse("$date 00:00:00", $tz);
        // MySQL DATETIME (without fractional seconds) silently drops
        // sub-second precision, so .999999 would be truncated and the
        // boundary check could miss an appointment ending at 23:59:59.
        $dayEnd = Carbon::parse("$date 23:59:59", $tz);

        $booked = Appointment::forStaff($validated['staff_id'])
            ->whereNotIn('state_id', [Status::COMPLETED, Status::CANCELLED])
            ->where('start_date', '<', $dayEnd)
            ->where('end_date', '>', $dayStart)
            ->get(['start_date', 'end_date']);

        $availableSlots = [];
        // Compare against the business-timezone "today", not the server's
        // default timezone. isToday() without a timezone argument uses
        // now()->toDateString(), which can be off by a day for a UTC
        // server serving a Europe/Istanbul business.
        $now = Carbon::now($tz);
        $isToday = $date === $now->toDateString();

        foreach (Staff::WORK_BLOCKS as $block) {
            $blockStart = Carbon::parse("$date {$block['start']}", $tz);
            $blockEnd = Carbon::parse("$date {$block['end']}", $tz);

            if ($isToday && $blockStart->lt($now)) {
                $minutesSinceMidnight = $now->diffInMinutes($blockStart->copy()->startOfDay(), false);
                $roundedMinutes = (int) ceil($minutesSinceMidnight / 15) * 15;
                $slot = $blockStart->copy()->startOfDay()->addMinutes($roundedMinutes);
            } else {
                $slot = $blockStart->copy();
            }

            while ($slot->copy()->addMinutes($duration)->lte($blockEnd)) {
                if ($isToday && $slot->lt($now)) {
                    $slot->addMinutes(15);

                    continue;
                }

                $slotEnd = $slot->copy()->addMinutes($duration);

                $hasConflict = $booked->contains(function ($appt) use ($slot, $slotEnd) {
                    return $slot->lt($appt->end_date) && $slotEnd->gt($appt->start_date);
                });

                if (! $hasConflict) {
                    $availableSlots[] = $slot->format('H:i');
                }

                $slot->addMinutes(15);
            }
        }

        return response()->json(['available_slots' => $availableSlots]);
    }
}
