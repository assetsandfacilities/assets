<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Institutional email domains
    |--------------------------------------------------------------------------
    |
    | Registration is restricted to email addresses issued by the university.
    | An institutional address can only be created by NU itself, so the domain
    | is what actually proves affiliation — an OTP only proves inbox access.
    |
    | student_domain : required for ordinary (student) registration.
    | staff_domains  : additionally accepted ONLY for applicants who have
    |                  verified an Asset Management voucher (Program Chairs,
    |                  Deans, Executive Director, requestor staff). Set this to
    |                  an empty array to make the system strictly students-only.
    |
    */

    'student_domain' => env('NU_STUDENT_EMAIL_DOMAIN', 'students.nu-clark.edu.ph'),

    'staff_domains' => array_values(array_filter(explode(',', (string) env('NU_STAFF_EMAIL_DOMAINS', 'nu-clark.edu.ph')))),

    /*
    |--------------------------------------------------------------------------
    | Polite titles
    |--------------------------------------------------------------------------
    |
    | Offered to voucher holders at registration and editable by the Super
    | Admin, then printed with the name under the e-signature on charge slips
    | and activity proposals. Add entries here (e.g. 'Dr.', 'Engr.', 'Atty.')
    | and they appear in every title dropdown without further changes.
    |
    */

    'polite_titles' => ['Mr.', 'Ms.', 'Mrs.'],

    /*
    |--------------------------------------------------------------------------
    | One-time password (OTP) controls
    |--------------------------------------------------------------------------
    |
    | expiry_minutes   : how long a verification code stays usable.
    | cooldown_seconds : minimum wait between two codes for the same address,
    |                    shown as a countdown on the resend button.
    | resend_max       : resends allowed after the first code. Reaching the cap
    |                    locks that address for resend_lock_minutes.
    | resend_lock_minutes : the wait once the resend cap is used up.
    |
    | ip_max_attempts / ip_decay_minutes: cap per IP address. NOTE: a campus
    | network usually puts every student behind ONE public IP, so this cap is
    | deliberately looser than the per-address cap -- it exists to stop a script
    | cycling through many addresses, not to limit one person. If real students
    | start seeing "too many codes from this connection" during a registration
    | drive, raise NU_OTP_IP_MAX rather than lowering the per-address cap.
    |
    */

    'otp' => [
        'expiry_minutes' => (int) env('NU_OTP_EXPIRY_MINUTES', 2),
        'cooldown_seconds' => (int) env('NU_OTP_COOLDOWN_SECONDS', 60),
        'resend_max' => (int) env('NU_OTP_RESEND_MAX', 3),
        'resend_lock_minutes' => (int) env('NU_OTP_RESEND_LOCK_MINUTES', 50),
        'ip_max_attempts' => (int) env('NU_OTP_IP_MAX', 30),
        'ip_decay_minutes' => (int) env('NU_OTP_IP_DECAY_MINUTES', 15),
    ],

];
