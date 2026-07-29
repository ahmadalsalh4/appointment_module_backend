<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Working-hours checks shared between AppointmentController (booking) and
 * ServiceController (duration-change cascade). Returns true when a slot
 * is fully inside one of Staff::WORK_BLOCKS on the local date.
 */
class WorkingHoursChecker
{
    /**
     * @param  array<int, array{start: string, end: string}>  $blocks
     */
    public static function isWithin(
        Carbon $startDate,
        Carbon $endDate,
        array $blocks,
        string $tz = 'Europe/Istanbul',
    ): bool {
        $localStart = $startDate->copy()->setTimezone($tz);
        $localEnd = $endDate->copy()->setTimezone($tz);

        if ($localStart->toDateString() !== $localEnd->toDateString()) {
            return false;
        }

        $dateStr = $localStart->toDateString();

        foreach ($blocks as $block) {
            $blockStart = Carbon::parse("{$dateStr} {$block['start']}", $tz);
            $blockEnd = Carbon::parse("{$dateStr} {$block['end']}", $tz);

            if ($localStart->gte($blockStart) && $localEnd->lte($blockEnd)) {
                return true;
            }
        }

        return false;
    }
}