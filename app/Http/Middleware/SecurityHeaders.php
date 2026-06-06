<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private array $headers = [
        // Prevent clickjacking
        'X-Frame-Options' => 'DENY',
        // Stop MIME-type sniffing
        'X-Content-Type-Options' => 'nosniff',
        // Force HTTPS for 1 year, include subdomains
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
        // Referrer policy: never send full URL cross-origin
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // Disable dangerous browser features
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        // Opt out of FLoC / Topics API
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headers as $header => $value) {
            $response->headers->set($header, $value);
        }

        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        return $response;
    }

    private function buildCsp(): string
    {
        $frontendUrl = rtrim(config('app.frontend_url', ''), '/');
        $appUrl      = rtrim(config('app.url', ''), '/');

        $directives = [
            "default-src 'self'",
            // Scripts: only same-origin; adjust if you load from a CDN
            "script-src 'self'",
            // Styles: allow inline (needed for many SPA frameworks) — tighten with nonce if you can
            "style-src 'self' 'unsafe-inline'",
            // Images: same-origin + data URIs for base64 avatars + your S3 bucket domain
            "img-src 'self' data: blob: " . env('AWS_URL', '') . ' ' . env('ASSET_URL', ''),
            // Fonts: same-origin
            "font-src 'self' data:",
            // Forms must POST to same origin only
            "form-action 'self'",
            // API fetch calls: same-origin + frontend origin
            "connect-src 'self' {$appUrl} {$frontendUrl} wss://{$this->host($frontendUrl)}",
            // Restrict framing to same origin
            "frame-ancestors 'none'",
            // Disallow Flash/Java plugins
            "object-src 'none'",
            // Upgrade insecure requests to HTTPS automatically
            'upgrade-insecure-requests',
        ];

        return implode('; ', array_filter($directives));
    }

    private function host(string $url): string
    {
        $parsed = parse_url($url, PHP_URL_HOST);
        return $parsed ?: '';
    }
}
