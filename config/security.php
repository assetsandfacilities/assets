<?php

return [
    // Mobile/API access tokens are intentionally short-lived.
    'jwt_secret' => env('JWT_SECRET', ''),
    'jwt_ttl' => env('JWT_TTL', 30),
    'bcrypt_rounds' => env('BCRYPT_ROUNDS', 12),

    // Machine-to-machine procurement export. Keep the key only in server env vars.
    'erp_api_key' => env('ERP_API_KEY', ''),
    'erp_allowed_ips' => env('ERP_ALLOWED_IPS', ''),
];
