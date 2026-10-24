<?php

return [
    'enabled' => (bool) env('SMS_ENABLED', false),
    // A shared lock/token store is required across PHP workers.
    'cache_store' => env('SMS_CACHE_STORE', 'database'),
    'message' => env('SMS_LOGIN_MESSAGE', 'BuxoroAvto: sizning kirish kodingiz: :code. Kodni hech kimga bermang.'),
    'eskiz' => [
        'base_url' => 'https://notify.eskiz.uz/api',
        'email' => env('ESKIZ_EMAIL'),
        'password' => env('ESKIZ_PASSWORD'),
        'sender' => env('ESKIZ_SENDER', '4546'),
        'token_cache_seconds' => 3600,
        'timeout' => 8,
        'connect_timeout' => 3,
    ],
    'otp' => [
        'expires_seconds' => 300,
        'resend_seconds' => 60,
        'max_attempts' => 5,
        'phone_hourly_limit' => 3,
        'phone_daily_limit' => 10,
        'ip_hourly_limit' => 20,
        'ip_daily_limit' => 50,
        // Failed provider requests have separate limits; they are not successful sends.
        'phone_attempt_hourly_limit' => 10,
        'ip_attempt_hourly_limit' => 40,
    ],
];
