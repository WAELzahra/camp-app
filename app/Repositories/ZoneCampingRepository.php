<?php

namespace App\Repositories;

use App\Models\CampingZones;

class ZoneCampingRepository
{
    public function searchNearby($lat, $lng, $query = null, $radius = 0.2)
    {
        // Recherche simple de zones proches dans un carré lat/lng (± radius degrés)
        return CampingZones::whereBetween('lat', [$lat - $radius, $lat + $radius])
            ->whereBetween('lng', [$lng - $radius, $lng + $radius])
            ->when($query, function ($q) use ($query) {
                return $q->where('nom', 'like', "%$query%");
            })
            ->get()
            ->map(function ($zone) {
                return [
                    'name' => $zone->nom,
                    'description' => $zone->description,
                    'lat' => $zone->lat,
                    'lng' => $zone->lng,
                    'type' => $zone->type_activitee,
                    'status' => $zone->status,
                    'source' => 'internal',
                    'added_by' => $zone->added_by,
                    'image' => $zone->image,
                ];
            })
            ->toArray();
    }
}
