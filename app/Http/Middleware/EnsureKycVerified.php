<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks non-camper accounts from publishing listings or receiving payments
 * until an admin has verified their KYC documents (Task A-01).
 *
 * Campers are exempt: their KYC runs in parallel and never blocks actions.
 */
class EnsureKycVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isKycExempt() && !$user->isKycVerified()) {
            return response()->json([
                'success' => false,
                'error'   => 'kyc_required',
                'message' => 'Votre compte est en attente de vérification. Vous serez notifié une fois votre compte activé.',
            ], 403);
        }

        return $next($request);
    }
}
