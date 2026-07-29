<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Single source of truth for "now" in business timezone.
 *
 * The application default timezone is UTC (where Render runs), but the
 * business operates in Europe/Istanbul. Anything that compares a stored
 * appointment against "now" must use this helper, never Carbon::now()
 * or now() directly.
 */
class BusinessClock
{
    public const TIMEZONE = 'Europe/Istanbul';

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }
}
