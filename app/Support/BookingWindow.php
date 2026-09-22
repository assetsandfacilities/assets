<?php

namespace App\Support;

use Carbon\Carbon;

/**
 | Advance-notice rule for booking a venue.
 |
 | The panel asked for a minimum lead time so the Facilities Office is never
 | handed an activity that starts tomorrow: a proposal still has to travel
 | through the Adviser, the Dean, the SDAO and the directors before the date
 | arrives. The number of days lives in config/fmo.php.
 |
 | Both booking paths (Activity Proposal and the direct Reserve Facility form)
 | ask this class. The UI uses the same earliest date in its warning dialog and
 | the server-side validation remains authoritative, so a hand-crafted POST is
 | refused even when browser checks are bypassed.
 */
class BookingWindow
{
    public static function leadDays(): int
    {
        return max(0, (int) config('fmo.reservation_lead_days', 2));
    }

    /** Campus time, not server time: the rule is about calendar dates here. */
    public static function earliestStart(): Carbon
    {
        return Carbon::now('Asia/Manila')->startOfDay()->addDays(self::leadDays());
    }

    /** Validation rule for a start date/time field. */
    public static function startRule(): string
    {
        return 'after_or_equal:'.self::earliestStart()->toDateTimeString();
    }

    /** Value for a date or datetime-local input's min attribute. */
    public static function minAttribute(bool $withTime = true): string
    {
        return self::earliestStart()->format($withTime ? 'Y-m-d\TH:i' : 'Y-m-d');
    }

    /** One-line explanation for the form, or null when there is no lead time. */
    public static function notice(): ?string
    {
        $days = self::leadDays();
        if ($days < 1) {
            return null;
        }

        return 'Activities must be booked at least '.$days.' '.\Illuminate\Support\Str::plural('day', $days)
            .' in advance. The earliest date you can choose is '
            .self::earliestStart()->format('F j, Y').'.';
    }

    /** Message shown when a submitted date is inside the lead time. */
    public static function violationMessage(): string
    {
        $days = self::leadDays();

        return 'This activity is too soon. Bookings need at least '.$days.' '
            .\Illuminate\Support\Str::plural('day', $days).' of advance notice, so the earliest date you can choose is '
            .self::earliestStart()->format('F j, Y').'.';
    }
}
