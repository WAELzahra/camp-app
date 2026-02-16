<?php

return [
    'paths' => [
        'api/*',
        'login',
        'logout',
        'sanctum/csrf-cookie',
        'user',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
        'email/verification-notification',
        'verify-by-token',
        'verify-by-code',
        'resend-verification',
        'verification-status/*',
        'broadcasting/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];