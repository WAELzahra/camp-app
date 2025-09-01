<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Signales;
use App\Models\User;
use App\Models\Camping_zones;

class SignalesSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $zone = Camping_zones::first();

        Signales::create([
            'user_id' => $user->id,
            'zone_id' => $zone->id,
            'target_id' => null,
            'type' => 'zone',
            'contenu' => 'Zone dangereuse, présence d’animaux.',
        ]);
    }
}
