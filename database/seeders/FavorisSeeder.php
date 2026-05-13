<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Favoris;
use App\Models\User;
use App\Models\CampingCentre;
use App\Models\Camping_Zones;
use App\Models\Events;
use Carbon\Carbon;

class FavorisSeeder extends Seeder
{
    private $favorisData = [
        // Format: [user_email, target_type, target_name]
        ['user@example.com', 'centre', 'Luxury Camping Resort'],
        ['user@example.com', 'zone', 'Forest Paradise'],
        ['user@example.com', 'event', 'Summer Camp 2024'],
        ['camper@example.com', 'centre', 'Mountain View Camp'],
        ['camper@example.com', 'zone', 'Lake Side'],
        ['guide@example.com', 'event', 'Hiking Adventure'],
        ['guide@example.com', 'zone', 'Mountain Trail'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->favorisData as $data) {
            $this->createFavoriFromData($data);
        }

        // Also create random favorites for all users
        $this->createRandomFavorites();
    }

    private function createFavoriFromData($data)
    {
        $user = User::where('email', $data[0])->first();
        if (!$user) return;

        $targetId = $this->findTargetId($data[1], $data[2]);
        if (!$targetId) return;

        Favoris::firstOrCreate([
            'user_id' => $user->id,
            'target_id' => $targetId,
            'type' => $data[1],
        ]);
    }

    private function findTargetId($type, $name)
    {
        switch ($type) {
            case 'centre':
                $centre = CampingCentre::where('name', 'LIKE', "%{$name}%")->first();
                return $centre ? $centre->id : null;
            case 'zone':
                $zone = Camping_Zones::where('nom', 'LIKE', "%{$name}%")->first();
                return $zone ? $zone->id : null;
            case 'event':
                $event = Events::where('titre', 'LIKE', "%{$name}%")->first();
                return $event ? $event->id : null;
            default:
                return null;
        }
    }

    private function createRandomFavorites()
    {
        $users = User::where('email_verified_at', '!=', null)->get();
        $centers = CampingCentre::all();
        $zones = Camping_Zones::all();
        $events = Events::where('date_debut', '>', Carbon::now())->get();

        foreach ($users as $user) {
            // Add 1-3 random favorites for each user
            $count = rand(1, 3);
            
            for ($i = 0; $i < $count; $i++) {
                $type = rand(0, 2);
                
                switch ($type) {
                    case 0: // centre
                        if ($centers->isNotEmpty()) {
                            $center = $centers->random();
                            Favoris::firstOrCreate([
                                'user_id' => $user->id,
                                'target_id' => $center->id,
                                'type' => 'centre',
                            ]);
                        }
                        break;
                        
                    case 1: // zone
                        if ($zones->isNotEmpty()) {
                            $zone = $zones->random();
                            Favoris::firstOrCreate([
                                'user_id' => $user->id,
                                'target_id' => $zone->id,
                                'type' => 'zone',
                            ]);
                        }
                        break;
                        
                    case 2: // event
                        if ($events->isNotEmpty()) {
                            $event = $events->random();
                            Favoris::firstOrCreate([
                                'user_id' => $user->id,
                                'target_id' => $event->id,
                                'type' => 'event',
                            ]);
                        }
                        break;
                }
            }
        }
    }
}