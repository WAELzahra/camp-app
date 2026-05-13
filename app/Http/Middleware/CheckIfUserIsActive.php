<?php

// app/Http/Middleware/CheckIfUserIsActive.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckIfUserIsActive
{
    /**
     * Block inactive accounts from business-critical routes.
     * Does NOT log the user out — pending users may still access
     * dashboard, settings and profile-completion routes.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->is_active == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending admin approval. You cannot perform this action yet.',
                'pending_approval' => true,
            ], 403);
        }

        return $next($request);
    }
}
