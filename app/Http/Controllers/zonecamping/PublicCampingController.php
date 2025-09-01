<?php

namespace App\Http\Controllers\zonecamping;

use App\Http\Controllers\Controller;
use App\Models\Camping_Zones;
use Illuminate\Http\Request;

class PublicCampingController extends Controller
{
       public function zonesWithCentres()
    {
        // Récupérer toutes les zones actives avec leur centre
        $zones = Camping_Zones::with(['centre.user.profile', 'centre.user.profileCentre'])
            ->where('status', '1')
            ->get();

        $zonesForMap = $zones->map(function($zone) {
            return [
                "id" => $zone->id,
                "nom_zone" => $zone->nom,
                "lat" => $zone->lat,
                "lng" => $zone->lng,
                "description" => $zone->description,
                "centre" => $zone->centre ? $zone->centre->fullDetails() : null
            ];
        });

        return response()->json($zonesForMap);
    }

    /**
     * Affichage d'un centre spécifique
     */
    public function showCentre($id)
    {
        $centre = \App\Models\CampingCentre::with(['zones', 'user.profile', 'user.profileCentre'])
            ->findOrFail($id);

        return response()->json($centre->fullDetails());
    }

    // Lister les zones avec centre et filtrage intelligent
    public function zonesWithFilters(Request $request)
{
    $query = \App\Models\Camping_Zones::with(['centre.user.profile', 'centre.user.profileCentre'])
        ->where('status', '1');

    if ($request->filled('type_activitee')) {
        $query->where('type_activitee', $request->type_activitee);
    }

    if ($request->filled('danger_level')) {
        $query->where('danger_level', $request->danger_level);
    }

    if ($request->filled('centre_id')) {
        $query->where('centre_id', $request->centre_id);
    }

    $zones = $query->get();

    $zonesForMap = $zones->map(fn($zone) => [
        "id" => $zone->id,
        "nom_zone" => $zone->nom,
        "lat" => $zone->lat,
        "lng" => $zone->lng,
        "description" => $zone->description,
        "centre" => $zone->centre ? $zone->centre->fullDetails() : null
    ]);

    return response()->json($zonesForMap);
    }   

   // Récupérer toutes les zones autour d’un point (rayon en km)
    public function nearbyZones(Request $request)
{
    $request->validate([
        'lat' => 'required|numeric',
        'lng' => 'required|numeric',
        'radius' => 'nullable|numeric'
    ]);

    $radius = $request->radius ?? 10;

    $zones = \App\Models\Camping_Zones::select('*')
        ->selectRaw(
            '(6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance',
            [$request->lat, $request->lng, $request->lat]
        )
        ->having('distance', '<=', $radius)
        ->orderBy('distance')
        ->get();

    return response()->json($zones->map(fn($zone) => [
        "id" => $zone->id,
        "nom_zone" => $zone->nom,
        "lat" => $zone->lat,
        "lng" => $zone->lng,
        "distance_km" => round($zone->distance, 2),
        "centre" => $zone->centre ? $zone->centre->fullDetails() : null
    ]));
    }

}

