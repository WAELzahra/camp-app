<?php

namespace App\Services\AI;

use App\Models\CampingZone;
use App\Services\AI\Weather\WeatherAdapterInterface;
use App\Services\AI\Weather\WeatherForecast;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const RISK_PRIORITY = ['extreme' => 4, 'high' => 3, 'moderate' => 2, 'low' => 1];

    public function __construct(
        private readonly WeatherAdapterInterface $adapter,
    ) {}

    /**
     * Fetch a weather forecast for a camping zone.
     * Returns null if the zone has no coordinates OR if the adapter throws.
     * A weather failure MUST NEVER break the trip planner.
     */
    public function getForecastForZone(CampingZone $zone, int $days = 3): ?WeatherForecast
    {
        if (! $zone->lat || ! $zone->lng) {
            return null;
        }

        try {
            $start    = (int) round(microtime(true) * 1000);
            $forecast = $this->adapter->getForecast((float) $zone->lat, (float) $zone->lng, $days);
            $elapsed  = (int) round(microtime(true) * 1000) - $start;

            Log::info('weather_fetch', [
                'zone_id'       => $zone->id,
                'lat'           => $zone->lat,
                'lng'           => $zone->lng,
                'risk_level'    => $this->getOverallRiskLevel($forecast),
                'cached'        => $forecast->isMocked ? false : true, // live adapters cache internally
                'response_time' => $elapsed,
            ]);

            return $forecast;

        } catch (\Throwable $e) {
            Log::warning('weather_fetch_failed', [
                'zone_id' => $zone->id,
                'lat'     => $zone->lat,
                'lng'     => $zone->lng,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build a concise string for injection into the LLM prompt (≤ 300 chars).
     */
    public function getWeatherSummaryForPrompt(?WeatherForecast $forecast): string
    {
        if ($forecast === null) {
            return 'Données météo non disponibles pour cette zone.';
        }

        $parts = [];
        foreach (array_slice($forecast->daily, 0, 3) as $i => $day) {
            $label  = 'J+' . ($i + 1);
            $parts[] = "{$label} {$day->tempMin}–{$day->tempMax}°C {$day->mainCondition}";
        }

        $overallRisk  = $this->getOverallRiskLevel($forecast);
        $riskSuffix   = '';
        if ($overallRisk !== 'low' && ! empty($forecast->daily)) {
            // Include the first non-"low" factor across all days
            foreach ($forecast->daily as $day) {
                if ($day->riskLevel !== 'low' && ! empty($day->riskFactors)) {
                    $riskSuffix = ' ' . $day->riskFactors[0];
                    break;
                }
            }
        }

        $summary = 'Météo prévue (' . $forecast->location . ') : '
            . implode(', ', $parts)
            . '. Risque global : ' . $overallRisk . '.' . $riskSuffix;

        return mb_substr($summary, 0, 300);
    }

    /**
     * Return the highest risk level across all days.
     */
    public function getOverallRiskLevel(?WeatherForecast $forecast): string
    {
        if ($forecast === null || empty($forecast->daily)) {
            return 'unknown';
        }

        $max = 'low';
        foreach ($forecast->daily as $day) {
            if ((self::RISK_PRIORITY[$day->riskLevel] ?? 0) > (self::RISK_PRIORITY[$max] ?? 0)) {
                $max = $day->riskLevel;
            }
        }

        return $max;
    }

    /**
     * Returns true if the forecast warrants a visible warning banner.
     */
    public function shouldWarnUser(?WeatherForecast $forecast): bool
    {
        return in_array($this->getOverallRiskLevel($forecast), ['high', 'extreme'], true);
    }
}
