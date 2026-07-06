<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CustomNotification;
use App\Services\Kyc\KycDocumentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin KYC verification console (Task A-01 §1F).
 * Every approval/rejection is appended to kyc_notes (internal only) and to
 * the application log with the acting admin's id.
 */
class AdminKycController extends Controller
{
    /** GET /admin/kyc/pending — oldest submissions first. */
    public function pending(Request $request): JsonResponse
    {
        $users = User::with('role:id,name')
            ->where('kyc_status', 'pending')
            ->whereNotNull('kyc_submitted_at')
            ->orderBy('kyc_submitted_at')
            ->paginate($request->integer('per_page', 20));

        $users->getCollection()->transform(fn (User $u) => [
            'id'                => $u->id,
            'name'              => trim($u->first_name . ' ' . $u->last_name),
            'email'             => $u->email,
            'role'              => $u->role?->name,
            'kyc_document_type' => $u->kyc_document_type,
            'kyc_submitted_at'  => $u->kyc_submitted_at,
            'has_document'      => !empty($u->kyc_document_path),
        ]);

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * GET /admin/kyc/{user} — full KYC detail for the admin user page
     * (Users management → user details).
     */
    public function show(int $userId): JsonResponse
    {
        $user = User::with('role:id,name', 'profile.profileCentre:id,profile_id,legal_document,document_legal_type')
            ->findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'id'                 => $user->id,
                'name'               => trim($user->first_name . ' ' . $user->last_name),
                'role'               => $user->role?->name,
                'kyc_status'         => $user->kyc_status,
                'account_status'     => $user->account_status,
                'kyc_document_type'  => $user->kyc_document_type,
                'kyc_submitted_at'   => $user->kyc_submitted_at,
                'kyc_verified_at'    => $user->kyc_verified_at,
                'kyc_rejected_at'    => $user->kyc_rejected_at,
                'has_document'       => !empty($user->kyc_document_path),
                'has_legal_document' => !empty($user->profile?->profileCentre?->legal_document),
                'legal_document_type' => $user->profile?->profileCentre?->document_legal_type,
            ],
        ]);
    }

    /**
     * GET /admin/kyc/{user}/legal-document — decrypt and stream a centre's
     * legal document (encrypted at rest).
     */
    public function downloadLegalDocument(int $userId, \App\Services\Storage\EncryptedDocumentStore $store)
    {
        $user = User::with('profile.profileCentre:id,profile_id,legal_document')->findOrFail($userId);
        $path = $user->profile?->profileCentre?->legal_document;

        abort_if(empty($path), 404, 'Aucun document légal.');
        abort_unless($store->exists($path), 404, 'Fichier introuvable.');

        $this->audit(request()->user()->id, $user->id, 'legal_document_downloaded');

        return response($store->get($path))
            ->header('Content-Type', $store->mimeType($path))
            ->header('Content-Disposition', 'inline; filename="legal-' . $user->id . '.' . $store->extension($path) . '"');
    }

    /**
     * GET /admin/kyc/{user}/document — decrypt and stream the document with
     * its real mime type (inline) so the browser can display it directly.
     */
    public function downloadDocument(int $userId, KycDocumentStore $store)
    {
        $user = User::findOrFail($userId);

        abort_if(empty($user->kyc_document_path), 404, 'Aucun document.');
        abort_unless($store->exists($user->kyc_document_path), 404, 'Fichier introuvable.');

        $filename = 'kyc-' . $user->id . '.' . $store->extension($user->kyc_document_path);

        $this->audit(request()->user()->id, $user->id, 'document_downloaded');

        return response($store->get($user->kyc_document_path))
            ->header('Content-Type', $store->mimeType($user->kyc_document_path))
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /** POST /admin/kyc/{user}/approve */
    public function approve(Request $request, int $userId): JsonResponse
    {
        $user  = User::findOrFail($userId);
        $admin = $request->user();

        $user->update([
            'kyc_status'      => 'verified',
            'kyc_verified_at' => now(),
            'kyc_rejected_at' => null,
            'account_status'  => 'active',
            'kyc_notes'       => $this->appendNote($user, "approved by admin #{$admin->id}"),
        ]);

        $this->audit($admin->id, $user->id, 'approved');
        $user->notify(new CustomNotification([
            'title'   => 'Compte vérifié',
            'content' => 'Votre identité a été vérifiée. Votre compte est désormais pleinement actif.',
            'type'    => 'account_verified',
            'priority' => 'high',
        ]));

        return response()->json(['success' => true, 'message' => 'Utilisateur approuvé.']);
    }

    /**
     * POST /admin/kyc/{user}/reject
     * The optional reason goes into internal notes only — the user never sees it.
     */
    public function reject(Request $request, int $userId): JsonResponse
    {
        $user  = User::findOrFail($userId);
        $admin = $request->user();
        $note  = $request->input('note'); // internal only

        $user->update([
            'kyc_status'      => 'rejected',
            'kyc_rejected_at' => now(),
            'account_status'  => $user->isKycExempt() ? $user->account_status : 'pending_kyc',
            'kyc_notes'       => $this->appendNote($user, "rejected by admin #{$admin->id}" . ($note ? " — {$note}" : '')),
        ]);

        $this->audit($admin->id, $user->id, 'rejected');
        $user->notify(new CustomNotification([
            'title'   => 'Vérification refusée',
            'content' => 'Votre demande a été rejetée. Veuillez contacter le support.',
            'type'    => 'security_alert',
            'priority' => 'high',
        ]));

        return response()->json(['success' => true, 'message' => 'Utilisateur rejeté.']);
    }

    private function appendNote(User $user, string $line): string
    {
        return trim(($user->kyc_notes ? $user->kyc_notes . "\n" : '') . '[' . now()->toDateTimeString() . '] ' . $line);
    }

    private function audit(int $adminId, int $targetUserId, string $action): void
    {
        Log::info("KYC {$action}", ['admin_id' => $adminId, 'user_id' => $targetUserId, 'at' => now()->toIso8601String()]);
    }
}
