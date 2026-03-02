<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Feedbacks;
use App\Models\User;
use App\Models\Camping_zones;

class FeedbacksZonesSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $zone = Camping_zones::first();

        Feedbacks::create([
            'user_id' => $user->id,
            'zone_id' => $zone->id,
            'contenu' => 'Zone très propre et agréable.',
            'note' => 5,
            'type' => 'zone',
            'status' => 'approved',
        ]);
    }
}
