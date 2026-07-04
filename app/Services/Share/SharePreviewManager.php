<?php

namespace App\Services\Share;

use Illuminate\Support\Facades\Cache;

/**
 * Routes a share request to the right provider and caches the result
 * so crawler bursts (FB fetches a link several times) stay cheap.
 */
class SharePreviewManager
{
    /** @var array<string, SharePreviewProvider> keyed by type */
    private array $providers = [];

    private const CACHE_TTL_SECONDS = 600;

    /** @param iterable<SharePreviewProvider> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->type()] = $provider;
        }
    }

    public function supports(string $type): bool
    {
        return isset($this->providers[$type]);
    }

    public function resolve(string $type, string $slug): ?SharePreview
    {
        if (!$this->supports($type)) {
            return null;
        }

        $cacheKey = 'share-preview:' . $type . ':' . sha1($slug);

        $cached = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->providers[$type]->resolve($slug)?->toArray() ?? false
        );

        return $cached === false ? null : SharePreview::fromArray($cached);
    }
}
