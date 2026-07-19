<?php

return [
    'email_verification' => [
        'required' => filter_var(env('YUEJING_EMAIL_VERIFICATION_REQUIRED', false), FILTER_VALIDATE_BOOLEAN),
    ],
    'admin' => [
        'name' => env('YUEJING_ADMIN_NAME', ''),
        'email' => env('YUEJING_ADMIN_EMAIL', ''),
        'password' => env('YUEJING_ADMIN_PASSWORD', ''),
    ],
    'pagination' => max(1, (int) env('YUEJING_PAGINATION', 15)),
    'two_factor' => [
        'issuer' => '阅境',
        'totp_period' => max(1, (int) env('YUEJING_TOTP_PERIOD', 30)),
        'totp_window' => max(0, (int) env('YUEJING_TOTP_WINDOW', 1)),
        'challenge_lifetime' => max(1, (int) env('YUEJING_TOTP_CHALLENGE_LIFETIME', 10)),
        'max_attempts' => max(1, (int) env('YUEJING_TOTP_MAX_ATTEMPTS', 5)),
    ],
];
