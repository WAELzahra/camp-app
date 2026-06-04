<?php

namespace App\Services\AI\Weather;

final class WeatherForecast
{
    public function __construct(
        public readonly float  $lat,
        public readonly float  $lng,
        public readonly string $location,
        public readonly array  $daily,       // DailyWeather[]
        public readonly string $fetchedAt,
        public readonly bool   $isMocked,
    ) {}

    public function toArray(): array
    {
        return [
            'lat'        => $this->lat,
            'lng'        => $this->lng,
            'location'   => $this->location,
            'daily'      => array_map(fn (DailyWeather $d) => $d->toArray(), $this->daily),
            'fetched_at' => $this->fetchedAt,
            'is_mocked'  => $this->isMocked,
        ];
    }
}
