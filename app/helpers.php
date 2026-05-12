<?php

use Illuminate\Support\Facades\Storage;

if (!function_exists('storage_url')) {
    function storage_url(?string $path): ?string
    {
        if (!$path) return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
        return Storage::disk('public')->url($path);
    }
}
