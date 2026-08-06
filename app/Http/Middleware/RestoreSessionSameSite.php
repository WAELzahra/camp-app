<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful hardcodes
 * session.same_site to "lax" for every stateful API request, regardless of
 * config/env. That's correct when frontend and backend share a registrable
 * domain (Sanctum's supported SPA setup), but breaks cookie-based auth when
 * they're on genuinely separate domains (e.g. a preview/test deployment on
 * a different host than production) since cross-site XHR requests never
 * carry SameSite=Lax cookies. This runs immediately after Sanctum's
 * middleware to put back whatever SESSION_SAME_SITE actually specifies,
 * reading it from session.same_site_override — a copy Sanctum never touches.
 */
class RestoreSessionSameSite
{
    public function handle(Request $request, Closure $next)
    {
        config(['session.same_site' => config('session.same_site_override')]);

        return $next($request);
    }
}
