<?php

namespace App\Services\Share\Providers;

use App\Services\Share\SharePreviewProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Shared plumbing for concrete providers: slug/id resolution and
 * text/image normalisation. Subclasses only describe their entity.
 */
abstract class AbstractShareProvider implements SharePreviewProvider
{
    protected const DESCRIPTION_LIMIT = 200;

    /**
     * Find a model by slug, falling back to a numeric or url-safe-base64
     * encoded id (the SPA links with `slug || encodeId(id)`).
     */
    protected function findBySlugOrId(Builder $query, string $slug, string $slugColumn = 'slug'): ?Model
    {
        $found = (clone $query)->where($slugColumn, $slug)->first();
        if ($found) {
            return $found;
        }

        $id = $this->decodeId($slug);

        return $id ? (clone $query)->whereKey($id)->first() : null;
    }

    /** Accepts a plain numeric id or the SPA's url-safe base64 encoding of one. */
    protected function decodeId(string $value): ?int
    {
        if (ctype_digit($value)) {
            return (int) $value;
        }

        $base64  = strtr($value, '-_', '+/');
        $decoded = base64_decode($base64 . str_repeat('=', (4 - strlen($base64) % 4) % 4), true);

        return ($decoded !== false && ctype_digit($decoded)) ? (int) $decoded : null;
    }

    /** Plain-text, crawler-friendly description. */
    protected function sanitize(?string $text): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($text ?? '')));

        return Str::limit($clean, static::DESCRIPTION_LIMIT);
    }

    /** Absolute image URL or null (storage_url handles local + R2 disks). */
    protected function imageUrl(?string $path): ?string
    {
        return $path ? storage_url($path) : null;
    }

    /**
     * Best photo from the shared photos table for one owning column
     * (cover first, then most recent). Returns the raw storage path.
     */
    protected function latestPhotoPath(string $ownerColumn, ?int $ownerId): ?string
    {
        if (!$ownerId) {
            return null;
        }

        return \App\Models\Photo::where($ownerColumn, $ownerId)
            ->whereNotNull('path_to_img')
            ->orderByRaw('is_cover DESC, id DESC')
            ->value('path_to_img');
    }
}
