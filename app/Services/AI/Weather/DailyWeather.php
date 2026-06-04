<?php

namespace App\Services\AI\Weather;

final class DailyWeather
{
    public function __construct(
        public readonly string $date,             // Y-m-d
        public readonly float  $tempMin,          // celsius
        public readonly float  $tempMax,          // celsius
        public readonly float  $windSpeedMax,     // m/s
        public readonly float  $precipitationMm,  // mm accumulated
        public readonly int    $humidityAvg,      // percent
        public readonly string $mainCondition,    // e.g. "Rain", "Clear", "Thunderstorm"
        public readonly string $description,      // e.g. "light rain"
        public readonly string $icon,             // OWM icon code e.g. "10d"
        public readonly string $riskLevel,        // low | moderate | high | extreme
        public readonly array  $riskFactors,      // strings explaining active risks
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
