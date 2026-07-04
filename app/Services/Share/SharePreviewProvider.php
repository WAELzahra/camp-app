<?php

namespace App\Services\Share;

/**
 * One provider per shareable entity type (centre, zone, event, …).
 * Register new types by adding a provider — nothing else changes (OCP).
 */
interface SharePreviewProvider
{
    /** The {type} URL segment this provider handles, e.g. "centre". */
    public function type(): string;

    /** Resolve a preview from a slug (or encoded/numeric id). Null when not found. */
    public function resolve(string $slug): ?SharePreview;
}
