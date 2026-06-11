<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives groupe (organizer) accounts a camper-style profile so the AI group
 * matching engine can score and cluster them as candidate groups.
 *
 * Without this, only campeurs have a `profile_campeurs` row, so
 * GroupMatchingService falls back to matching campers against campers and the
 * "/group-profile" links never resolve to a real group. Spreading group
 * profiles across skill/budget/comfort buckets ensures every K-Means cluster
 * contains a few groups, so campers actually get group matches.
 *
 * Idempotent: skips groupe profiles that already exist; never touches campeurs.
 */
class GroupeProfileCampeursSeeder extends Seeder
{
    private const SKILLS   = ['beginner', 'intermediate', 'advanced'];
    private const COMFORTS = ['basic', 'standard', 'glamping'];
    private const BUDGETS  = ['budget', 'moderate', 'premium'];

    private const STYLE_SETS = [
        ['groupe', 'social', 'aventure', 'randonnée'],
        ['groupe', 'nature', 'découverte', 'team-building'],
        ['groupe', 'montagne', 'trekking', 'bivouac'],
        ['groupe', 'côtier', 'détente', 'baignade'],
        ['groupe', 'désert', 'expédition', 'stargazing'],
    ];

    private const ACTIVITY_SETS = [
        ['Randonnée', 'Soirée feu de camp', 'Jeux collectifs', 'Camping groupe'],
        ['Team-building', 'Observation nature', 'Randonnée', 'Bivouac'],
        ['Escalade', 'Trekking', 'Camping groupe', 'Navigation'],
        ['Baignade', 'Kayak', 'Pique-nique', 'Beach games'],
        ['Bivouac désertique', 'Stargazing', 'Randonnée saharienne', 'Méharée'],
    ];

    public function run(): void
    {
        $now = now()->toDateTimeString();

        $organizerRoleId = DB::table('roles')->where('name', 'organizer')->value('id');
        if (! $organizerRoleId) {
            $this->command?->warn('⚠️  No organizer/groupe role — skipping.');
            return;
        }

        $groupes = DB::table('users as u')
            ->join('profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.role_id', $organizerRoleId)
            ->select('u.id as user_id', 'p.id as profile_id')
            ->get();

        $existing = DB::table('profile_campeurs')
            ->whereIn('profile_id', $groupes->pluck('profile_id'))
            ->pluck('profile_id')
            ->flip();

        $rows = [];

        foreach ($groupes as $i => $g) {
            if ($existing->has($g->profile_id)) {
                continue;
            }

            // Deterministic spread across buckets so groups populate every cluster.
            $skill   = self::SKILLS[$i % 3];
            $comfort = self::COMFORTS[($i + 1) % 3];
            $budget  = self::BUDGETS[($i + 2) % 3];
            $set     = $i % count(self::STYLE_SETS);

            $rows[] = [
                'profile_id'            => $g->profile_id,
                'skill_level'           => $skill,
                'comfort_level'         => $comfort,
                'budget_range'          => $budget,
                'preferred_trip_styles' => json_encode(self::STYLE_SETS[$set], JSON_UNESCAPED_UNICODE),
                'preferred_activities'  => json_encode(self::ACTIVITY_SETS[$set], JSON_UNESCAPED_UNICODE),
                'gear_preferences'      => json_encode([], JSON_UNESCAPED_UNICODE),
                'total_trips'           => rand(5, 25),
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('profile_campeurs')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($rows) . ' groupe profile_campeurs records inserted.');
    }
}
