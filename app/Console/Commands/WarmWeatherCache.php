<?php

namespace App\Console\Commands;

use App\Models\CampingZone;
use App\Services\AI\WeatherService;
use Illuminate\Console\Command;

class WarmWeatherCache extends Command
{
    protected $signature   = 'ai:warm-weather {--limit=20 : Number of zones to warm}';
    protected $description = 'Pre-fetches and caches weather for the most-rated camping zones.';

    public function handle(WeatherService $weatherService): int
    {
        $limit = (int) $this->option('limit');

        $zones = CampingZone::whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('status', true)
            ->where('is_closed', false)
            ->orderByDesc('rating')
            ->limit($limit)
            ->get(['id', 'nom', 'lat', 'lng', 'rating']);

        $this->info("Warming weather cache for {$zones->count()} zones (limit: {$limit})...");

        $success = 0;
        $failure = 0;

        foreach ($zones as $zone) {
            try {
                $forecast = $weatherService->getForecastForZone($zone);
                if ($forecast) {
                    $risk = $weatherService->getOverallRiskLevel($forecast);
                    $this->info("  ✓ [{$zone->id}] {$zone->nom} — risk: {$risk}");
                    $success++;
                } else {
                    $this->warn("  ✗ [{$zone->id}] {$zone->nom} — no coordinates or no data");
                    $failure++;
                }
            } catch (\Throwable $e) {
                $this->warn("  ✗ [{$zone->id}] {$zone->nom} — error: {$e->getMessage()}");
                $failure++;
            }
        }

        $this->info("\nDone. Success: {$success} / Failure: {$failure}");

        return self::SUCCESS;
    }
}
