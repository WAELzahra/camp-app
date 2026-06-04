<?php

namespace App\Services\AI\Pricing;

final class DemandSignal
{
    public function __construct(
        public readonly int    $entityId,
        public readonly string $entityType,       // zone | materielle
        public readonly int    $recentBookings,   // bookings in last 30 days
        public readonly int    $recentFavorites,  // favorites/bookmarks in last 30 days
        public readonly float  $avgRating,
        public readonly int    $reviewCount,
        public readonly float  $avgCategoryPrice, // avg price of similar items
        public readonly float  $currentPrice,
        public readonly string $demandLevel,      // low | moderate | high | peak
        public readonly array  $trendingTags,
        public readonly string $season,           // summer | spring | autumn | winter
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
