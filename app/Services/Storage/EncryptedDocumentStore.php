<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Encrypted-at-rest document storage on the application's DEFAULT disk
 * (local in development, Cloudflare R2 in production). Contents are
 * encrypted with the app key before writing, so even a public bucket only
 * ever holds unreadable ciphertext; decryption happens exclusively in the
 * authorized endpoints that call get().
 */
class EncryptedDocumentStore
{
    protected const MIME_BY_EXTENSION = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];

    /** Encrypt and store an uploaded document under $directory; returns the path. */
    public function put(string $directory, UploadedFile $file): string
    {
        $path = trim($directory, '/') . '/' . now()->format('YmdHis') . '_' . uniqid()
            . '.' . strtolower($file->getClientOriginalExtension()) . '.enc';

        Storage::put($path, Crypt::encrypt($file->get()));

        return $path;
    }

    public function exists(?string $path): bool
    {
        return $path !== null && Storage::exists($path);
    }

    /**
     * Raw file contents. Falls back to the stored bytes when the file
     * pre-dates encryption (legacy plaintext uploads).
     */
    public function get(string $path): string
    {
        $raw = Storage::get($path);

        try {
            return Crypt::decrypt($raw);
        } catch (\Throwable) {
            return $raw; // legacy unencrypted document
        }
    }

    public function delete(?string $path): void
    {
        if ($this->exists($path)) {
            Storage::delete($path);
        }
    }

    /** Original file extension (the trailing ".enc" stripped). */
    public function extension(string $path): string
    {
        return strtolower(pathinfo(preg_replace('/\.enc$/', '', $path), PATHINFO_EXTENSION)) ?: 'bin';
    }

    /** Real content type so browsers can render the document inline. */
    public function mimeType(string $path): string
    {
        return static::MIME_BY_EXTENSION[$this->extension($path)] ?? 'application/octet-stream';
    }
}
