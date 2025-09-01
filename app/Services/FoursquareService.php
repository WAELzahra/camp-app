<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FoursquareService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.foursquare.key');
    }

    public function searchCamping($lat, $lng, $query = 'camping')
    {
        $url = 'https://api.foursquare.com/v3/places/search';

        $params = [
            'query' => $query,
            'll' => "$lat,$lng",
            'radius' => 20000,
            'limit' => 20
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Accept' => 'application/json'
        ])->get($url, $params);

        $data = $response->json();

        return collect($data['results'] ?? [])->map(function ($item) {
            return [
                'name' => $item['name'] ?? 'Inconnu',
                'description' => $item['location']['formatted_address'] ?? null,
                'lat' => $item['geocodes']['main']['latitude'] ?? null,
                'lng' => $item['geocodes']['main']['longitude'] ?? null,
                'type' => $item['categories'][0]['name'] ?? 'camp_site',
                'status' => 'external',
                'source' => 'foursquare',
                'added_by' => null,
                'image' => null,
                'is_public' => true,
                'adresse' => $item['location']['formatted_address'] ?? null,
            ];
        })->toArray();
    }
}
