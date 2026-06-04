<?php

namespace App\Services\AI\Weather;

class MockWeatherAdapter implements WeatherAdapterInterface
{
    public function getForecast(float $lat, float $lng, int $days = 3): WeatherForecast
    {
        $daily = [
            new DailyWeather(
                date:            now()->format('Y-m-d'),
                tempMin:         15.0,
                tempMax:         24.0,
                windSpeedMax:    3.0,
                precipitationMm: 0.0,
                humidityAvg:     55,
                mainCondition:   'Clear',
                description:     'ciel dégagé',
                icon:            '01d',
                riskLevel:       'low',
                riskFactors:     ['Conditions favorables au camping'],
            ),
            new DailyWeather(
                date:            now()->addDay()->format('Y-m-d'),
                tempMin:         13.0,
                tempMax:         21.0,
                windSpeedMax:    6.0,
                precipitationMm: 2.0,
                humidityAvg:     65,
                mainCondition:   'Clouds',
                description:     'nuageux',
                icon:            '04d',
                riskLevel:       'low',
                riskFactors:     ['Conditions favorables au camping'],
            ),
            new DailyWeather(
                date:            now()->addDays(2)->format('Y-m-d'),
                tempMin:         10.0,
                tempMax:         17.0,
                windSpeedMax:    9.0,
                precipitationMm: 8.0,
                humidityAvg:     80,
                mainCondition:   'Rain',
                description:     'pluie légère',
                icon:            '10d',
                riskLevel:       'moderate',
                riskFactors:     [
                    'Pluie modérée prévue — prévoir imperméable',
                    'Vent modéré',
                    'Nuits fraîches — sac de couchage chaud recommandé',
                ],
            ),
        ];

        return new WeatherForecast(
            lat:       36.95,  // Tabarka
            lng:       8.76,
            location:  'Tabarka (mock)',
            daily:     array_slice($daily, 0, $days),
            fetchedAt: now()->toIso8601String(),
            isMocked:  true,
        );
    }
}
