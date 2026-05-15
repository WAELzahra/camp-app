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
    protected $except = [
        // API routes use Sanctum Bearer token auth and do not need CSRF cookies.
        // Only web routes that are genuinely stateless need exemption here.
        'sanctum/csrf-cookie',
        'broadcasting/auth',
    ];
}