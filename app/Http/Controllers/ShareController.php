<?php

namespace App\Http\Controllers;

use App\Services\Share\SharePreview;
use App\Services\Share\SharePreviewManager;
use Illuminate\Http\Response;

/**
 * Serves crawler-readable Open Graph / Twitter Card HTML for SPA detail
 * pages. Social bots are redirected here by the frontend host; humans who
 * land here are bounced straight back to the SPA page.
 */
class ShareController extends Controller
{
    public function __construct(private readonly SharePreviewManager $previews)
    {
    }

    public function show(string $type, string $slug): Response
    {
        $preview = $this->previews->resolve($type, $slug) ?? $this->fallback();

        $frontendUrl = $this->frontendBaseUrl();

        return response()
            ->view('share.preview', [
                'preview'      => $preview,
                'canonicalUrl' => $frontendUrl . $preview->frontendPath,
                'siteName'     => 'TunisiaCamp',
                'defaultImage' => $frontendUrl . '/logo.png',
            ])
            ->header('Cache-Control', 'public, max-age=600')
            ->header('X-Robots-Tag', 'noindex'); // canonical lives on the SPA
    }

    private function fallback(): SharePreview
    {
        return new SharePreview(
            title:        'TunisiaCamp',
            description:  'Camping, maisons d\'hôte, zones et équipements outdoor en Tunisie.',
            image:        null,
            frontendPath: '/',
        );
    }

    private function frontendBaseUrl(): string
    {
        // FRONTEND_URL may hold a comma-separated list (shared with CORS config)
        $first = trim(explode(',', (string) config('app.frontend_url'))[0]);

        return rtrim($first ?: 'https://www.tunisiacamp.tn', '/');
    }
}
