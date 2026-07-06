<?php

namespace App\Services\Kyc;

use App\Services\Storage\EncryptedDocumentStore;
use Illuminate\Http\UploadedFile;

/**
 * Encrypted KYC document storage (Task A-01 §1C) — thin specialization of
 * the generic encrypted store, scoped to the kyc/{userId} directory.
 */
class KycDocumentStore extends EncryptedDocumentStore
{
    /** Encrypt and store an uploaded KYC document; returns the storage path. */
    public function putForUser(int $userId, UploadedFile $file): string
    {
        return $this->put('kyc/' . $userId, $file);
    }
}
