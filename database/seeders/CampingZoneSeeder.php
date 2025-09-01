<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Camping_zones;

class CampingZoneSeeder extends Seeder
{
    public function run()
    {
       Camping_zones::create([
    'nom' => 'Camping Aïn Draham',
    'type_activitee' => 'nature',
    'is_public' => true,
    'description' => 'Zone forestière populaire à Aïn Draham',
    'adresse' => 'Aïn Draham, Jendouba',
    'danger_level' => 'low', // correspond à ENUM
    'status' => true,
    'lat' => 36.7721,
    'lng' => 8.7016
]);

Camping_zones::create([
    'nom' => 'Camping Beni M’tir',
    'type_activitee' => 'lac',
    'is_public' => true,
    'description' => 'Proche du barrage de Beni M’tir',
    'adresse' => 'Beni M’tir, Jendouba',
    'danger_level' => 'moderate', // correspond à ENUM
    'status' => true,
    'lat' => 36.7985,
    'lng' => 8.7450
]);

    }
}
