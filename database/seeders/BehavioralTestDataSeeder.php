<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds targeted data so acceptance tests for BehavioralProfileService work:
 *
 *  - user_id=11  gets 3 additional approved centre reservations (total→5)
 *                + coastal zone favorites
 *                + positive coastal feedbacks
 *                + their static profile preferred_trip_styles set to forest/randonnée
 *                  (so Test E: behavioral 'coastal' beats static 'forest')
 *
 * Idempotent: guards against duplicate inserts on repeated runs.
 */
class BehavioralTestDataSeeder extends Seeder
{
    private const TARGET_USER    = 11;
    private const COASTAL_ZONES  = [1, 2, 6, 7];   // Cap Angela, Cap Serrat, Plage Sounine, Plage Ras Engela
    private const CENTRE_IDS     = [95, 96, 97, 99, 100]; // valid centre owner user_ids

    public function run(): void
    {
        // ── 1. Extra centre reservations for user_id=11 ──────────────────────
        $existing = DB::table('reservations_centres')
            ->where('user_id', self::TARGET_USER)
            ->where('status', 'approved')
            ->count();

        $extraNeeded = max(0, 5 - $existing); // bring total to 5

        $baseDate = now()->subMonths(4);
        for ($i = 0; $i < $extraNeeded; $i++) {
            $start    = $baseDate->copy()->addDays($i * 25);
            $centreId = self::CENTRE_IDS[$i % count(self::CENTRE_IDS)];

            DB::table('reservations_centres')->insertOrIgnore([
                'user_id'           => self::TARGET_USER,
                'centre_id'         => $centreId,
                'date_debut'        => $start->format('Y-m-d'),
                'date_fin'          => $start->copy()->addDays(3)->format('Y-m-d'),
                'nights'            => 3,
                'nbr_place'         => 4,
                'group_skill_level' => 'intermediate',
                'trip_purpose'      => 'Camping côtier',
                'status'            => 'approved',
                'total_price'       => 120.00 + ($i * 15),
                'payment_method'    => 'card',
                'service_count'     => 0,
                'discount_amount'   => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        // ── 2. Coastal zone favorites for user_id=11 ─────────────────────────
        foreach (self::COASTAL_ZONES as $zoneId) {
            DB::table('favorites')->insertOrIgnore([
                'user_id'          => self::TARGET_USER,
                'favoritable_id'   => $zoneId,
                'favoritable_type' => 'zone',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // ── 3. Positive feedbacks on coastal zones for user_id=11 ────────────
        foreach (self::COASTAL_ZONES as $zoneId) {
            DB::table('feedbacks')->insertOrIgnore([
                'user_id'    => self::TARGET_USER,
                'zone_id'    => $zoneId,
                'note'       => 5,
                'contenu'    => 'Zone côtière magnifique, plage propre et accessible.',
                'type'       => 'zone',
                'status'     => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── 4. Update static profile of user_id=11 to prefer forest/randonnée
        //      (so behavioral 'coastal' clearly overrides static in Test E) ────
        $profileId = DB::table('profiles')
            ->where('user_id', self::TARGET_USER)
            ->value('id');

        if ($profileId) {
            DB::table('profile_campeurs')
                ->where('profile_id', $profileId)
                ->update([
                    'preferred_trip_styles' => json_encode(['forêt', 'randonnée', 'nature']),
                    'skill_level'           => 'beginner',
                    'budget_range'          => 'budget',
                    'updated_at'            => now(),
                ]);
        }

        $this->command?->info('✅ BehavioralTestDataSeeder: data seeded for user_id=' . self::TARGET_USER);
    }
}
