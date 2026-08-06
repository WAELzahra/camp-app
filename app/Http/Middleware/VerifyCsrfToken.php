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
        // Credential-based Bearer token issuance (Postman/API clients, and
        // cross-domain deployments where third-party cookies are blocked).
        // No session cookie is relied on here, so there is nothing for CSRF
        // to protect — the endpoint already requires the actual password.
        'api/token-login',
    ];
}