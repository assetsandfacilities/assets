<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reservation Lead Days
    |--------------------------------------------------------------------------
    |
    | Minimum advance notice, in days, before an activity may be scheduled.
    | 0 means a requestor may book for today; 2 (the panel's requirement) means
    | a request filed on the 18th can be for the 20th at the earliest. The value
    | drives the reservation warning dialog and the server-side validation rule
    | through App\Support\BookingWindow. The UI does not auto-correct a user's
    | chosen date; it explains the rule and asks them to choose again.
    |
    */

    'reservation_lead_days' => (int) env('FMO_RESERVATION_LEAD_DAYS', 2),

];
