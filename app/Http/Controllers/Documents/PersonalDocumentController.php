<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Storage\EncryptedDocumentStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Authorized access to encrypted personal documents (CIN, guide certificate,
 * group patente). Owners see their own; admins see anyone's (audited).
 * Legacy plaintext files uploaded before encryption are still served
 * (decrypt falls back to raw bytes; public-disk fallback for old paths).
 */
class PersonalDocumentController extends Controller
{
    private const KINDS = ['cin', 'certificat', 'patente', 'cin_commercant', 'registre_commerce'];

    /** GET /my/documents/{kind} — the owner views their own document. */
    public function showOwn(Request $request, string $kind, EncryptedDocumentStore $store)
    {
        return $this->stream($request->user(), $kind, $store);
    }

    /** GET /admin/users/{user}/documents/{kind} — admin view, audited. */
    public function showForAdmin(Request $request, int $userId, string $kind, EncryptedDocumentStore $store)
    {
        $user = User::findOrFail($userId);

        Log::info('Personal document viewed by admin', [
            'admin_id' => $request->user()->id,
            'user_id'  => $user->id,
            'kind'     => $kind,
        ]);

        return $this->stream($user, $kind, $store);
    }

    private function stream(User $user, string $kind, EncryptedDocumentStore $store)
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        $profile = $user->profile;
        $path = match ($kind) {
            'cin'        => $profile?->cin_path,
            'certificat' => $profile?->profileGuide?->certificat_path,
            'patente'    => $profile?->profileGroupe?->patente_path,
            'cin_commercant'    => $profile?->profileFournisseur?->cin_commercant_path,
            'registre_commerce' => $profile?->profileFournisseur?->registre_commerce_path,
        };

        abort_if(empty($path), 404, 'Aucun document.');

        if ($store->exists($path)) {
            $content = $store->get($path);
        } elseif (Storage::disk('public')->exists($path)) {
            // Legacy plaintext upload from before encryption was introduced
            $content = Storage::disk('public')->get($path);
        } else {
            abort(404, 'Fichier introuvable.');
        }

        return response($content)
            ->header('Content-Type', $store->mimeType($path))
            ->header('Content-Disposition', 'inline; filename="' . $kind . '-' . $user->id . '.' . $store->extension($path) . '"');
    }
}
