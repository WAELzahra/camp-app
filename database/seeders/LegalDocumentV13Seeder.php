<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use App\Services\LegalConsentService;
use Illuminate\Database\Seeder;

/**
 * Publishes version 1.3 of every legal document type (cgu, cgv,
 * mentions_legales, confidentialite).
 *
 * This is a housekeeping revision, not a substantive change to legal
 * obligations: it removes personal references (founder names, personal
 * emails) from the public-facing legal pages in favour of generic
 * "Tunisia Camp owners" wording and the official contact@tunisiacamp.tn
 * address. The underlying DB-tracked content never carried personal
 * references (already generic since the base seeder), so it is republished
 * unchanged under a new version — the version bump alone is what forces
 * every user to re-acknowledge the (now-current) documents, aligning all
 * four types on a single version number.
 *
 * Uses LegalConsentService::publishVersion() so cache invalidation and
 * the deactivate-then-create transaction are handled centrally rather than
 * duplicated here (unlike the earlier per-version seeders).
 */
class LegalDocumentV13Seeder extends Seeder
{
    private const VERSION = '1.3';

    public function run(): void
    {
        foreach (['cgu', 'cgv', 'mentions_legales', 'confidentialite'] as $type) {
            $current = LegalDocument::where('type', $type)->where('is_active', true)->first();
            if (!$current) {
                $this->command?->warn("No active {$type} document found — skipped.");
                continue;
            }
            if (LegalDocument::where('type', $type)->where('version', self::VERSION)->exists()) {
                $this->command?->info("{$type} v" . self::VERSION . ' already exists — skipped.');
                continue;
            }

            LegalConsentService::publishVersion(
                type: $type,
                version: self::VERSION,
                effectiveDate: now()->toDateString(),
                contentFr: $current->content_fr,
                contentEn: $current->content_en,
                contentAr: $current->content_ar,
            );

            $this->command?->info("{$type} v" . self::VERSION . ' published.');
        }
    }
}
