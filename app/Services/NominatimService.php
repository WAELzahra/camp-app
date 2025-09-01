<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NominatimService
{
    public function searchCamping($lat, $lng, $query = 'camping')
    {
        $url = "https://nominatim.openstreetmap.org/search";

        $params = [
            'q' => $query,
            'format' => 'json',
            'addressdetails' => 1,
            'extratags' => 1,
            'limit' => 20,
            'bounded' => 1,
            // vue box autour de la position
            'viewbox' => ($lng - 0.2) . ',' . ($lat + 0.2) . ',' . ($lng + 0.2) . ',' . ($lat - 0.2),
        ];

        $response = Http::withHeaders([
            'User-Agent' => 'CampingApp/1.0 (contact@campingapp.com)'
        ])->get($url, $params);

        if ($response->failed()) {
            return [];
        }

        return collect($response->json())->map(function ($place) {
            return [
                'name' => $place['display_name'] ?? 'Inconnu',
                'description' => $place['type'] ?? null,
                'lat' => $place['lat'] ?? null,
                'lng' => $place['lon'] ?? null,
                'type' => $place['type'] ?? 'camp_site',
                'status' => 'external',
                'source' => 'nominatim',
                'added_by' => null,
                'image' => null,
            ];
        })->toArray();
    }
}
