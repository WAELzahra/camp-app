<?php

namespace App\Http\Middleware;

use App\Http\Resources\LegalDocumentResource;
use App\Services\LegalConsentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * RequiresLegalAcceptance
 *
 * Blocks access to business-critical routes (reservations, payments) when the
 * authenticated user has not yet accepted all active legal documents.
 *
 * Returns HTTP 451 (Unavailable For Legal Reasons) so the frontend can
 * distinguish this specific case from a generic 403.
 *
 * Apply selectively on routes — do NOT add to global middleware or you will
 * create a loop with the /api/legal/* endpoints.
 */
class RequiresLegalAcceptance
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        $pending = LegalConsentService::getPendingForUser($user);

        if ($pending->isNotEmpty()) {
            return response()->json([
                'success'               => false,
                'message'               => 'Please accept the updated legal documents before continuing.',
                'requires_legal_acceptance' => true,
                'pending_documents'     => LegalDocumentResource::collection($pending),
            ], 451);
        }

        return $next($request);
    }
}
