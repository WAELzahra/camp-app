<?php

namespace App\Http\Controllers\Kyc;

use App\Http\Controllers\Controller;
use App\Services\Kyc\KycDocumentStore;
use App\Services\Storage\EncryptedDocumentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User-facing KYC endpoints (Task A-01).
 * Documents are stored encrypted on the private local disk; the raw path is
 * never returned to the user (hidden on the User model).
 */
class KycController extends Controller
{
    public const DOCUMENT_TYPES = ['cin', 'passeport', 'registre_commerce', 'autre'];

    /** GET /kyc/status — current status for the authenticated user. */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'kyc_status'        => $user->kyc_status,
                'account_status'    => $user->account_status,
                'kyc_submitted_at'  => $user->kyc_submitted_at,
                'kyc_document_type' => $user->kyc_document_type,
                'has_document'      => !empty($user->kyc_document_path),
            ],
        ]);
    }

    /**
     * POST /kyc/documents — upload (or re-upload after rejection).
     * Re-upload always resets the status back to 'pending'.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document'      => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'document_type' => 'required|string|in:' . implode(',', self::DOCUMENT_TYPES),
        ]);

        $user  = $request->user();
        $store = app(KycDocumentStore::class);

        // Encrypted at rest on the default disk (R2 in production) —
        // only admin endpoints can decrypt it back.
        $path = $store->putForUser($user->id, $request->file('document'));

        // Remove the previous document to avoid orphaned sensitive files
        $store->delete($user->kyc_document_path);

        $user->update([
            'kyc_status'        => 'pending',
            'kyc_submitted_at'  => now(),
            'kyc_rejected_at'   => null,
            'kyc_document_type' => $validated['document_type'],
            'kyc_document_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document soumis. Votre identité est en cours de vérification.',
            'data'    => ['kyc_status' => 'pending'],
        ]);
    }

    /**
     * GET /kyc/document — the user views their OWN submitted KYC document
     * (encrypted at rest; decrypted here, streamed inline so the profile
     * page can keep showing it after a refresh).
     */
    public function document(Request $request, KycDocumentStore $store)
    {
        $path = $request->user()->kyc_document_path;

        abort_if(empty($path), 404, 'Aucun document.');
        abort_unless($store->exists($path), 404, 'Fichier introuvable.');

        return response($store->get($path))
            ->header('Content-Type', $store->mimeType($path))
            ->header('Content-Disposition', 'inline; filename="kyc-document.' . $store->extension($path) . '"');
    }

    /**
     * GET /kyc/legal-document — a centre owner views their own legal
     * document (stored encrypted; decrypted here, streamed inline).
     */
    public function legalDocument(Request $request, EncryptedDocumentStore $store)
    {
        $path = $request->user()->profile?->profileCentre?->legal_document;

        abort_if(empty($path), 404, 'Aucun document.');
        abort_unless($store->exists($path), 404, 'Fichier introuvable.');

        return response($store->get($path))
            ->header('Content-Type', $store->mimeType($path))
            ->header('Content-Disposition', 'inline; filename="legal-document.' . $store->extension($path) . '"');
    }
}
