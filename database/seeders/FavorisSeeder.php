<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the `favorites` table.
 * favoritable_type ENUM: profile | centre | zone | equipment | annonce
 *  - centre    -> profile_centres.id
 *  - zone      -> camping_zones.id
 *  - equipment -> materielles.id
 *  - annonce   -> annonces.id
 *  - profile   -> profiles.id
 */
class FavorisSeeder extends Seeder
{
    public function run(): void
    {
        $userIds    = DB::table('users')->pluck('id')->toArray();
        $centreIds  = DB::table('profile_centres')->pluck('id')->toArray();
        $zoneIds    = DB::table('camping_zones')->pluck('id')->toArray();
        $matIds     = DB::table('materielles')->pluck('id')->toArray();
        $annonceIds = DB::table('annonces')->pluck('id')->toArray();
        $profileIds = DB::table('profiles')->pluck('id')->toArray();

        if (empty($userIds)) {
            $this->command->warn('No users found. Skipping FavorisSeeder.');
            return;
        }

        $pools = array_filter([
            'centre'    => $centreIds,
            'zone'      => $zoneIds,
            'equipment' => $matIds,
            'annonce'   => $annonceIds,
            'profile'   => $profileIds,
        ]);

        $favorites = [];
        $seen = [];

        foreach ($userIds as $userId) {
            // 2 to 5 favorites per user across the available types
            $count = rand(2, 5);
            for ($i = 0; $i < $count; $i++) {
                $type = array_rand($pools);
                $targetId = $pools[$type][array_rand($pools[$type])];

                $key = "$userId|$targetId|$type";
                if (isset($seen[$key])) {
                    continue; // respect the unique (user_id, favoritable_id, favoritable_type) index
                }
                $seen[$key] = true;

                $favorites[] = [
                    'user_id'          => $userId,
                    'favoritable_id'   => $targetId,
                    'favoritable_type' => $type,
                    'created_at'       => now()->subDays(rand(0, 60)),
                    'updated_at'       => now(),
                ];
            }
        }

        DB::table('favorites')->insert($favorites);
        $this->command->info(count($favorites) . ' favorites created.');
    }
}
