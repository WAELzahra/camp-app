<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Services\LegalConsentService;
use Illuminate\Console\Command;

/**
 * Publish a new version of a legal document.
 *
 * Usage:
 *   php artisan legal:publish-version cgu 2.0
 *   php artisan legal:publish-version cgu 2.0 --effective-date=2026-09-01
 *   php artisan legal:publish-version cgu 2.0 --content-fr="..." --content-en="..." --content-ar="..."
 *
 * Without --content-* flags the command copies the content from the currently
 * active version of that type and only bumps the version number. This is the
 * right workflow when you only change text in the frontend JSX but still need
 * users to formally re-accept.
 *
 * After publishing, every user who has not yet accepted the new row will see
 * the acceptance modal on their next page load.
 */
class LegalPublishVersion extends Command
{
    protected $signature = 'legal:publish-version
        {type : Document type: cgu | cgv | mentions_legales | confidentialite}
        {version : New version string, e.g. "2.0"}
        {--effective-date= : ISO date the version takes effect (defaults to today)}
        {--content-fr= : French content (defaults to current active version)}
        {--content-en= : English content (defaults to current active version)}
        {--content-ar= : Arabic content (defaults to current active version)}
        {--force : Skip confirmation prompt}';

    protected $description = 'Publish a new version of a legal document, deactivating the previous one and requiring all users to re-accept.';

    private const VALID_TYPES = ['cgu', 'cgv', 'mentions_legales', 'confidentialite'];

    public function handle(): int
    {
        $type    = $this->argument('type');
        $version = $this->argument('version');

        if (!in_array($type, self::VALID_TYPES)) {
            $this->error("Invalid type '{$type}'. Must be one of: " . implode(', ', self::VALID_TYPES));
            return self::FAILURE;
        }

        $effectiveDate = $this->option('effective-date') ?: now()->toDateString();

        // Resolve content: use provided flags or copy from current active version.
        $current = LegalDocument::active()->ofType($type)->first();

        $contentFr = $this->option('content-fr') ?: ($current?->content_fr ?? '');
        $contentEn = $this->option('content-en') ?: ($current?->content_en ?? '');
        $contentAr = $this->option('content-ar') ?: ($current?->content_ar ?? '');

        if (!$contentFr || !$contentEn || !$contentAr) {
            $this->error("No existing content for type '{$type}' and no --content-* provided.");
            return self::FAILURE;
        }

        $currentVersion = $current?->version ?? 'none';

        $this->table(['Field', 'Value'], [
            ['Type',           $type],
            ['New version',    $version],
            ['Previous version', $currentVersion],
            ['Effective date', $effectiveDate],
        ]);

        if (!$this->option('force') && !$this->confirm("Publish this version? All users will be required to re-accept.")) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $document = LegalConsentService::publishVersion($type, $version, $effectiveDate, $contentFr, $contentEn, $contentAr);

        $this->info("✓ Published {$type} v{$version} (ID: {$document->id}). Previous version '{$currentVersion}' deactivated.");
        $this->info('All users will see the acceptance modal on their next login or page load.');

        return self::SUCCESS;
    }
}
