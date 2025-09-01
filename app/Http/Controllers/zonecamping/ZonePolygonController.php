<?php

namespace App\Http\Controllers\zonecamping;

use Illuminate\Http\Request;
use App\Models\ZonePolygon;   
use App\Http\Controllers\Controller;

class ZonePolygonController extends Controller
{

// Créer un nouveau polygone
   public function store(Request $request)
{
    $data = $request->validate([
        'zone_id' => 'required|exists:camping_zones,id',
        'coordinates' => 'required|array|min:3', // au moins 3 points pour un polygone
        'coordinates.*.lat' => 'required|numeric',
        'coordinates.*.lng' => 'required|numeric', 
    ]);

    $polygon = ZonePolygon::create($data);

    return response()->json($polygon, 201);
}

// Mettre à jour un polygone
public function update(Request $request, $id)
{
    $polygon = ZonePolygon::findOrFail($id);

    $data = $request->validate([
        'coordinates' => 'required|array|min:3',
        'coordinates.*.lat' => 'required|numeric',
        'coordinates.*.lng' => 'required|numeric',
    ]);

    $polygon->update($data);

    return response()->json($polygon);
}

// Supprimer un polygone
public function destroy($id)
{
    $polygon = ZonePolygon::findOrFail($id);
    $polygon->delete();

    return response()->json(['message' => 'Polygone supprimé']);
}

// Lister les polygones par zone

public function listByZone($zoneId)
{
    $polygons = ZonePolygon::where('zone_id', $zoneId)->get();

    $geoJson = [
        'type' => 'FeatureCollection',
        'features' => $polygons->map(function ($polygon) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        ...collect($polygon->coordinates)->map(fn($coord) => [$coord['lng'], $coord['lat']])
                    ]],
                ],
                'properties' => [
                    'zone_id' => $polygon->zone_id,
                    'polygon_id' => $polygon->id,
                ],
            ];
        }),
    ];

    return response()->json($geoJson);
}


}
