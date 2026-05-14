<?php

use Illuminate\Support\Facades\Storage;

if (!function_exists('storage_url')) {
    function storage_url(?string $path): ?string
    {
        if (!$path) return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;

        // Strip the leading "storage/" prefix that some older code paths include,
        // since Storage::url() adds its own prefix based on the configured disk.
        $clean = ltrim(preg_replace('#^storage/#', '', $path), '/');

        // Prefer an explicit R2_PUBLIC_URL (set in production Railway env).
        $r2Base = env('R2_PUBLIC_URL');
        if ($r2Base) {
            return rtrim($r2Base, '/') . '/' . $clean;
        }

        // Fallback: use the DEFAULT filesystem disk's url() method.
        // In production FILESYSTEM_DISK=r2  → returns R2_PUBLIC_URL/{path}
        // In development FILESYSTEM_DISK=local/public → returns APP_URL/storage/{path}
        try {
            return Storage::url($clean);
        } catch (\Exception $e) {
            return Storage::disk('public')->url($clean);
        }
    }
}
