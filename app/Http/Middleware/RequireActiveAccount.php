<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * RequireActiveAccount
 *
 * Applied to business-critical routes (reservations, announcements, bookings, etc.).
 * Non-camper accounts must have is_active = 1 (admin-approved) to proceed.
 * Campers (role_id = 1) are always active and pass through immediately.
 */
class RequireActiveAccount
{
    // Role IDs that require admin approval before performing business actions
    private const PROVIDER_ROLES = [2, 3, 4, 5]; // groupe, centre, fournisseur, guide

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Only enforce for provider roles
        if (!in_array($user->role_id, self::PROVIDER_ROLES)) {
            return $next($request);
        }

        if ($user->is_active == 0) {
            return response()->json([
                'success'          => false,
                'message'          => 'Your account is awaiting admin approval. Complete your profile and wait for activation.',
                'pending_approval' => true,
            ], 403);
        }

        return $next($request);
    }
}
