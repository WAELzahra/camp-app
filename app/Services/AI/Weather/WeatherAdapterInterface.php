<?php

namespace App\Services\AI\Weather;

interface WeatherAdapterInterface
{
    /**
     * Retrieve a weather forecast for the given coordinates.
     *
     * @param  float  $lat   Latitude
     * @param  float  $lng   Longitude
     * @param  int    $days  Number of days (1–5 on OWM free tier)
     */
    public function getForecast(float $lat, float $lng, int $days = 3): WeatherForecast;
}
