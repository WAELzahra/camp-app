<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\CentreClaim;

class EnsureCentreNotPendingClaim
{
    /**
     * Block all write operations (PUT/POST/DELETE/PATCH) for centre users
     * who have a pending claim. GET requests are always allowed so they can
     * still view their current data.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->role_id === 3) {
            $hasPending = CentreClaim::where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists();

            if ($hasPending) {
                return response()->json([
                    'success'      => false,
                    'claim_locked' => true,
                    'message'      => 'Your claim is under review. Profile editing is locked until the admin approves or rejects your request.',
                ], 403);
            }
        }

        return $next($request);
    }
}
