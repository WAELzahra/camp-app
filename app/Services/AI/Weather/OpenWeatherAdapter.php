<?php

namespace App\Services\AI\Weather;

use App\Services\AI\RateLimitService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWeatherAdapter implements WeatherAdapterInterface
{
    // Free-tier soft limits (with buffer below OWM's 60/min, 1000/day)
    private const LIMIT_MINUTE = 50;
    private const LIMIT_DAY    = 900;
    private const WARN_MINUTE  = 40;
    private const WARN_DAY     = 720;

    // Cache for 3 hours — matches OWM free tier update frequency
    private const CACHE_TTL = 10800;

    public function __construct(
        private readonly RateLimitService $rateLimiter,
    ) {}

    public function getForecast(float $lat, float $lng, int $days = 3): WeatherForecast
    {
        // Round to 2 decimal places (~1 km precision) so nearby zones share cache
        $cacheKey = 'weather:forecast:' . round($lat, 2) . ':' . round($lng, 2);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lat, $lng, $days) {
            return $this->fetchFromApi($lat, $lng, $days);
        });
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function fetchFromApi(float $lat, float $lng, int $days): WeatherForecast
    {
        $this->enforceRateLimits();

        $cnt = min($days * 8, 40); // 8 slots per day, max 40 on free tier
        $url = rtrim((string) config('services.openweather.base_url'), '/') . '/forecast';

        $start = (int) round(microtime(true) * 1000);

        $response = Http::timeout(10)->get($url, [
            'lat'   => $lat,
            'lon'   => $lng,
            'units' => config('services.openweather.units', 'metric'),
            'cnt'   => $cnt,
            'appid' => config('services.openweather.key'),
        ]);

        $elapsed = (int) round(microtime(true) * 1000) - $start;

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OpenWeatherMap API error: ' . $response->status() . ' — ' . $response->body()
            );
        }

        $this->rateLimiter->increment('owm:rate:minute', 60);
        $this->rateLimiter->increment('owm:rate:day',    86400);
        $this->warnIfApproachingLimits();

        $data    = $response->json();
        $daily   = $this->aggregateDailyForecasts($data['list'] ?? [], $days);
        $location = $data['city']['name'] ?? "($lat, $lng)";

        Log::info('weather_fetch', [
            'lat'           => $lat,
            'lng'           => $lng,
            'risk_level'    => $this->highestRisk($daily),
            'cached'        => false,
            'response_time' => $elapsed,
        ]);

        return new WeatherForecast(
            lat:       $lat,
            lng:       $lng,
            location:  $location,
            daily:     $daily,
            fetchedAt: now()->toIso8601String(),
            isMocked:  false,
        );
    }

    private function enforceRateLimits(): void
    {
        if (! $this->rateLimiter->checkLimit('owm:rate:minute', self::LIMIT_MINUTE, 60)) {
            throw new \RuntimeException('OpenWeatherMap rate limit exceeded: minute');
        }
        if (! $this->rateLimiter->checkLimit('owm:rate:day', self::LIMIT_DAY, 86400)) {
            throw new \RuntimeException('OpenWeatherMap rate limit exceeded: day');
        }
    }

    private function warnIfApproachingLimits(): void
    {
        $minute = (int) Cache::get('owm:rate:minute', 0);
        $day    = (int) Cache::get('owm:rate:day',    0);

        if ($minute > self::WARN_MINUTE) {
            Log::warning('owm_rate_warning', ['window' => 'minute', 'used' => $minute, 'limit' => self::LIMIT_MINUTE]);
        }
        if ($day > self::WARN_DAY) {
            Log::warning('owm_rate_warning', ['window' => 'day', 'used' => $day, 'limit' => self::LIMIT_DAY]);
        }
    }

    /**
     * Aggregate 3-hour OWM slots into daily summaries (up to $days days).
     *
     * @return DailyWeather[]
     */
    private function aggregateDailyForecasts(array $slots, int $days): array
    {
        // Group slots by date
        $byDate = [];
        foreach ($slots as $slot) {
            $date = substr($slot['dt_txt'] ?? '', 0, 10); // "Y-m-d H:i:s" → "Y-m-d"
            if ($date) {
                $byDate[$date][] = $slot;
            }
        }

        $daily = [];
        foreach (array_slice(array_keys($byDate), 0, $days) as $date) {
            $daySlots = $byDate[$date];
            $daily[]  = $this->buildDailyWeather($date, $daySlots);
        }

        return $daily;
    }

    private function buildDailyWeather(string $date, array $slots): DailyWeather
    {
        $temps    = array_map(fn ($s) => (float) ($s['main']['temp']     ?? 15), $slots);
        $tempMins = array_map(fn ($s) => (float) ($s['main']['temp_min'] ?? 10), $slots);
        $tempMaxs = array_map(fn ($s) => (float) ($s['main']['temp_max'] ?? 20), $slots);
        $winds    = array_map(fn ($s) => (float) ($s['wind']['speed']    ?? 0),  $slots);
        $humids   = array_map(fn ($s) => (int)   ($s['main']['humidity'] ?? 50), $slots);
        $precips  = array_map(fn ($s) => (float) ($s['rain']['3h']       ?? 0),  $slots);

        $conditions = array_map(fn ($s) => $s['weather'][0]['main'] ?? 'Clear', $slots);
        $condFreq   = array_count_values($conditions);
        arsort($condFreq);
        $mainCondition = (string) array_key_first($condFreq);

        // Use the noon slot (12:00) for description and icon, fallback to first
        $refSlot = $slots[0];
        foreach ($slots as $slot) {
            if (str_contains($slot['dt_txt'] ?? '', '12:00:00')) {
                $refSlot = $slot;
                break;
            }
        }
        $description = $refSlot['weather'][0]['description'] ?? 'n/a';
        $icon        = $refSlot['weather'][0]['icon']        ?? '01d';

        $data = [
            'tempMin'         => round(min($tempMins), 1),
            'tempMax'         => round(max($tempMaxs), 1),
            'windSpeedMax'    => round(max($winds),    1),
            'precipitationMm' => round(array_sum($precips), 1),
            'humidityAvg'     => (int) round(array_sum($humids) / count($humids)),
            'mainCondition'   => $mainCondition,
        ];

        [$riskLevel, $riskFactors] = $this->assessRisk($data);

        return new DailyWeather(
            date:            $date,
            tempMin:         $data['tempMin'],
            tempMax:         $data['tempMax'],
            windSpeedMax:    $data['windSpeedMax'],
            precipitationMm: $data['precipitationMm'],
            humidityAvg:     $data['humidityAvg'],
            mainCondition:   $mainCondition,
            description:     $description,
            icon:            $icon,
            riskLevel:       $riskLevel,
            riskFactors:     $riskFactors,
        );
    }

    /**
     * Assess risk level from aggregated daily data.
     * Returns ['level' => string, 'factors' => string[]]
     */
    private function assessRisk(array $d): array
    {
        $factors  = [];
        $level    = 'low';

        // EXTREME
        if ($d['mainCondition'] === 'Thunderstorm') {
            $factors['extreme'][] = "Orage prévu — activité outdoor déconseillée";
        }
        if ($d['windSpeedMax'] > 20) {
            $factors['extreme'][] = "Vents extrêmes dangereux";
        }

        // HIGH
        if ($d['precipitationMm'] > 20) {
            $factors['high'][] = "Fortes précipitations prévues";
        }
        if ($d['windSpeedMax'] > 12) {
            $factors['high'][] = "Vents forts — prudence recommandée";
        }
        if ($d['tempMin'] < 2) {
            $factors['high'][] = "Risque de gel — équipement thermique obligatoire";
        }
        if ($d['tempMax'] > 40) {
            $factors['high'][] = "Chaleur extrême — risque de coup de chaleur";
        }

        // MODERATE
        if ($d['precipitationMm'] > 5) {
            $factors['moderate'][] = "Pluie modérée prévue — prévoir imperméable";
        }
        if ($d['windSpeedMax'] > 8) {
            $factors['moderate'][] = "Vent modéré";
        }
        if ($d['tempMin'] < 8) {
            $factors['moderate'][] = "Nuits fraîches — sac de couchage chaud recommandé";
        }
        if ($d['tempMax'] > 35) {
            $factors['moderate'][] = "Chaleur importante — hydratation essentielle";
        }
        if ($d['mainCondition'] === 'Snow') {
            $factors['moderate'][] = "Neige possible";
        }

        // Determine overall level (highest wins)
        if (! empty($factors['extreme'])) {
            $level = 'extreme';
        } elseif (! empty($factors['high'])) {
            $level = 'high';
        } elseif (! empty($factors['moderate'])) {
            $level = 'moderate';
        }

        // If nothing matched, add a low-risk positive factor
        $allFactors = array_merge(
            $factors['extreme'] ?? [],
            $factors['high']    ?? [],
            $factors['moderate'] ?? [],
        );
        if (empty($allFactors)) {
            $allFactors = ["Conditions favorables au camping"];
        }

        return [$level, $allFactors];
    }

    private function highestRisk(array $daily): string
    {
        $priority = ['extreme' => 4, 'high' => 3, 'moderate' => 2, 'low' => 1];
        $max      = 'low';
        foreach ($daily as $day) {
            if (($priority[$day->riskLevel] ?? 0) > ($priority[$max] ?? 0)) {
                $max = $day->riskLevel;
            }
        }
        return $max;
    }
}
