<?php

namespace App\Http\Controllers\zonecamping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Zone\StoreZonePolygonRequest;
use App\Http\Requests\Zone\UpdateZonePolygonRequest;
use App\Models\ZonePolygon;

class ZonePolygonController extends Controller
{
    // Créer un nouveau polygone
    public function store(StoreZonePolygonRequest $request)
    {
        $data = $request->validated();

        $polygon = ZonePolygon::create($data);

        return response()->json($polygon, 201);
    }

    // Mettre à jour un polygone
    public function update(UpdateZonePolygonRequest $request, $id)
    {
        $polygon = ZonePolygon::findOrFail($id);

        $data = $request->validated();

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
                            ...collect($polygon->coordinates)->map(fn ($coord) => [$coord['lng'], $coord['lat']]),
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
