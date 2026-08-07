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
        'sanctum/csrf-cookie',
        'broadcasting/auth',
        // Every api/* route is authenticated via a Sanctum Bearer token in the
        // Authorization header (see AuthProvider's token-login flow), not a
        // session cookie. CSRF exists to stop a forged request from riding on
        // a victim's ambient cookies — it has nothing to protect here, since a
        // cross-site page can neither read sessionStorage nor set a custom
        // Authorization header on the requests it forges. Sanctum's
        // EnsureFrontendRequestsAreStateful still classifies this origin as
        // "frontend" (it's in SANCTUM_STATEFUL_DOMAINS) and would otherwise
        // demand a CSRF token no cookie-less client can ever produce.
        'api/*',
    ];
}