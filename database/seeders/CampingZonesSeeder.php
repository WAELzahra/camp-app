<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Camping_zones;
use App\Models\User;

class CampingZonesSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first(); // utilisateur existant

        Camping_zones::create([
            'nom' => 'Aïn Draham',
            'type_activitee' => 'randonnée',
            'is_public' => true,
            'description' => 'Forêt dense, très prisée.',
            'adresse' => 'Nord-Ouest, Tunisie',
            'danger_level' => 'moderate',
            'status' => true,
            'lat' => 36.7783,
            'lng' => 8.7072,
            'image' => null,
            'source' => 'interne',
            'added_by' => $user->id,
        ]);
    }
}
