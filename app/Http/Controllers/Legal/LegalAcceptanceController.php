<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legal\AcceptLegalDocumentsRequest;
use App\Http\Resources\LegalDocumentResource;
use App\Services\LegalConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalAcceptanceController extends Controller
{
    /**
     * GET /api/legal/status
     *
     * Returns which active documents the authenticated user still needs to accept.
     * Frontend polls this once after login to decide whether to show the modal.
     */
    public function status(Request $request): JsonResponse
    {
        $user   = $request->user();
        $cached = LegalConsentService::getStatusForUser($user);

        // Fetch full document objects only when acceptance is actually needed,
        // keeping the common "all accepted" path a pure cache hit.
        $pending = $cached['needs_acceptance']
            ? LegalConsentService::getPendingForUser($user)
            : collect();

        return response()->json([
            'success'           => true,
            'needs_acceptance'  => $cached['needs_acceptance'],
            'pending_documents' => LegalDocumentResource::collection($pending),
        ]);
    }

    /**
     * POST /api/legal/accept
     *
     * Records acceptance for the supplied document IDs.
     * IP and user-agent are captured server-side for legal proof.
     */
    public function accept(AcceptLegalDocumentsRequest $request): JsonResponse
    {
        $user = $request->user();

        LegalConsentService::recordAcceptances(
            user:        $user,
            documentIds: $request->validated('document_ids'),
            ipAddress:   $request->ip(),
            userAgent:   $request->userAgent() ?? '',
            method:      'modal'
        );

        // Re-check whether anything is still pending after this batch.
        $stillPending = LegalConsentService::getPendingForUser($user);

        return response()->json([
            'success'          => true,
            'message'          => 'Acceptance recorded.',
            'needs_acceptance' => $stillPending->isNotEmpty(),
        ]);
    }

    /**
     * GET /api/legal/history
     *
     * The user's own acceptance history — useful for transparency / profile page.
     */
    public function history(Request $request): JsonResponse
    {
        $history = LegalConsentService::getHistoryForUser($request->user());

        $data = $history->map(fn ($acceptance) => [
            'document_type'    => $acceptance->legalDocument?->type,
            'document_version' => $acceptance->legalDocument?->version,
            'type_label'       => $acceptance->legalDocument?->type_label,
            'accepted_at'      => $acceptance->accepted_at->toIso8601String(),
            'method'           => $acceptance->acceptance_method,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }
}
