<?php

namespace App\Services;

use App\Repositories\ZoneCampingRepository;
use App\Services\NominatimService;

class ZoneSearchService
{
    protected $zoneRepo;
    protected $nominatimService;

    public function __construct(ZoneCampingRepository $zoneRepo, NominatimService $nominatimService)
    {
        $this->zoneRepo = $zoneRepo;
        $this->nominatimService = $nominatimService;
    }

    public function searchCamping($lat, $lng, $query = 'camping')
    {
        $internal = $this->zoneRepo->searchNearby($lat, $lng, $query);
        $external = $this->nominatimService->searchCamping($lat, $lng, $query);

        $merged = collect($internal);

        foreach ($external as $zone) {
            // évite doublons par lat/lng proche
            $exists = $merged->first(fn($item) =>
                abs($item['lat'] - $zone['lat']) < 0.001 &&
                abs($item['lng'] - $zone['lng']) < 0.001
            );
            if (!$exists) {
                $merged->push($zone);
            }
        }

        if ($merged->isEmpty()) {
            return ['error' => 'Aucune zone trouvée à cet emplacement'];
        }

        return $merged->values()->toArray();
    }
}
