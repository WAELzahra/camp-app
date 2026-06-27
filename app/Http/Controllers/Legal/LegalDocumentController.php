<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegalDocumentResource;
use App\Services\LegalConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves the publicly readable list of active legal documents.
 * No authentication required — the signup page needs to link to these.
 */
class LegalDocumentController extends Controller
{
    /** GET /api/legal/documents — list all active documents. */
    public function index(Request $request): JsonResponse
    {
        $documents = LegalConsentService::getActiveDocuments();

        return response()->json([
            'success' => true,
            'data'    => LegalDocumentResource::collection($documents),
        ]);
    }
}
