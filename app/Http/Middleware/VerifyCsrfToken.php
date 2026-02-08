<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    // protected $except = [
    //     //
    // ];

    protected $except = [
        'register',
        'login',
        'api/forgot-password',
        'api/verify-password',
        'api/reset-password',
        'api/send-verification',
        'resend-verification',


    ];
    
}
