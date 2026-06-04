<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

class RateLimitService
{
    private const KEY_MINUTE     = 'groq:rate:minute';
    private const KEY_DAY        = 'groq:rate:day';
    private const LIMIT_MINUTE   = 30;
    private const LIMIT_DAY      = 6000;

    /**
     * Returns true if the current counter for $key is below $maxRequests.
     * Does NOT increment the counter — call increment() after a successful call.
     */
    public function checkLimit(string $key, int $maxRequests, int $ttlSeconds): bool
    {
        $current = (int) Cache::get($key, 0);
        return $current < $maxRequests;
    }

    /**
     * Increments the named counter.
     * Uses Cache::increment() so that an existing TTL is never reset.
     * Initialises the key to 1 with the given TTL if it does not exist yet.
     */
    public function increment(string $key, int $ttlSeconds): void
    {
        // add() only sets the value when the key is absent — preserving TTL on
        // subsequent calls, unlike Cache::put() which resets the expiry.
        Cache::add($key, 0, $ttlSeconds);
        Cache::increment($key);
    }

    /**
     * Returns the current usage snapshot for both windows.
     * Used by the admin dashboard endpoint.
     */
    public function getUsage(): array
    {
        return [
            'minute'       => (int) Cache::get(self::KEY_MINUTE, 0),
            'day'          => (int) Cache::get(self::KEY_DAY,    0),
            'minute_limit' => self::LIMIT_MINUTE,
            'day_limit'    => self::LIMIT_DAY,
        ];
    }

    /**
     * Clears both rate-limit counters.
     * Intended for tests and local debugging only.
     */
    public function resetAll(): void
    {
        Cache::forget(self::KEY_MINUTE);
        Cache::forget(self::KEY_DAY);
    }
}
