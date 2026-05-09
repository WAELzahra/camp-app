<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfileCampeursSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('profile_campeurs')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $campeurRoleId = DB::table('roles')->where('name', 'campeur')->value('id');

        $campeurs = DB::table('users as u')
            ->join('profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.role_id', $campeurRoleId)
            ->select('u.id as user_id', 'u.preferences', 'p.id as profile_id')
            ->get();

        $rows = [];

        foreach ($campeurs as $c) {
            $prefs     = json_decode($c->preferences, true);
            $archetype = $prefs['archetype'] ?? 'C';
            $v         = $c->profile_id % 3; // deterministic variant: 0, 1, or 2

            [$skill, $comfort, $budget, $styles, $activities, $gear, $trips] =
                $this->archetypeData($archetype, $v);

            $rows[] = [
                'profile_id'            => $c->profile_id,
                'skill_level'           => $skill,
                'comfort_level'         => $comfort,
                'budget_range'          => $budget,
                'preferred_trip_styles' => json_encode($styles,     JSON_UNESCAPED_UNICODE),
                'preferred_activities'  => json_encode($activities, JSON_UNESCAPED_UNICODE),
                'gear_preferences'      => json_encode($gear,       JSON_UNESCAPED_UNICODE),
                'total_trips'           => $trips,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('profile_campeurs')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($rows) . ' profile_campeurs records inserted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // $v = 0|1|2  — deterministic variant within an archetype
    // Returns [skill, comfort, budget, styles[], activities[], gear[], trips]
    // ─────────────────────────────────────────────────────────────────────────
    private function archetypeData(string $archetype, int $v): array
    {
        $data = [

            // ── A: Budget Solo Adventurer ────────────────────────────────────
            'A' => [
                'skill'      => ['advanced', 'expert',   'advanced'],
                'comfort'    => ['basic',    'basic',    'basic'],
                'budget'     => ['budget',   'budget',   'budget'],
                'styles'     => [
                    ['solo', 'nature', 'aventure', 'montagne'],
                    ['solo', 'trekking', 'aventure', 'forêt'],
                    ['solo', 'montagne', 'bivouac', 'nature'],
                ],
                'activities' => [
                    ['Randonnée', 'Camping sauvage', 'Escalade', 'Photographie nature'],
                    ['Randonnée', 'Bivouac', 'Escalade', 'Exploration gorges'],
                    ['Randonnée nocturne', 'Camping sauvage', 'Bivouac', 'Photographie'],
                ],
                'gear'       => [
                    ['Tente légère 1p', 'Sac à dos 50L', 'Réchaud ultralight', 'Sac de couchage 3 saisons'],
                    ['Tente biplace légère', 'Sac à dos 40L', 'Réchaud gaz', 'Bâtons de randonnée'],
                    ['Tente solo bivouac', 'Sac à dos 55L', 'Kit bivouac', 'Lampe frontale 300lm'],
                ],
                'trips_min'  => 15,
                'trips_max'  => 40,
            ],

            // ── B: Family Camper ─────────────────────────────────────────────
            'B' => [
                'skill'      => ['beginner', 'beginner',  'beginner'],
                'comfort'    => ['standard', 'standard',  'glamping'],
                'budget'     => ['moderate', 'moderate',  'moderate'],
                'styles'     => [
                    ['famille', 'détente', 'bord de mer', 'nature douce'],
                    ['famille', 'plage', 'baignade', 'confort'],
                    ['famille', 'glamping', 'côtier', 'bien-être'],
                ],
                'activities' => [
                    ['Baignade', 'Pique-nique', 'Promenades nature', 'Activités aquatiques'],
                    ['Baignade', 'Jeux de plage', 'Pique-nique', 'Pêche'],
                    ['Baignade', 'Relaxation', 'Gastronomie', 'Activités enfants'],
                ],
                'gear'       => [
                    ['Tente familiale 4p', 'Matelas gonflable', 'Glacière', 'Chaises pliantes'],
                    ['Tente familiale 6p', 'Table de camping', 'Réchaud double feu', 'Parasol'],
                    ['Tente lodge 4p', 'Matelas double', 'Mobilier camping', 'Barbecue portable'],
                ],
                'trips_min'  => 1,
                'trips_max'  => 5,
            ],

            // ── C: Weekend Explorer ──────────────────────────────────────────
            'C' => [
                'skill'      => ['intermediate', 'intermediate', 'intermediate'],
                'comfort'    => ['standard',     'standard',     'standard'],
                'budget'     => ['moderate',     'moderate',     'moderate'],
                'styles'     => [
                    ['weekend', 'nature', 'découverte', 'photographie'],
                    ['weekend', 'forêt', 'randonnée', 'camping'],
                    ['weekend', 'montagne', 'exploration', 'nature'],
                ],
                'activities' => [
                    ['Randonnée', 'Photographie', 'Camping', 'Observation nature'],
                    ['Randonnée', 'Camping', 'Cyclisme VTT', 'Observation faune'],
                    ['Randonnée', 'Photographie', 'Exploration', 'Bivouac court'],
                ],
                'gear'       => [
                    ['Tente 2p', 'Sac à dos 35L', 'Réchaud gaz', 'Lampe frontale'],
                    ['Tente 3p', 'Sac à dos 40L', 'Kit cuisine camping', 'Bâtons trekking'],
                    ['Tente dôme 2p', 'Sac à dos 30L', 'Sac de couchage 2 saisons', 'Thermos'],
                ],
                'trips_min'  => 5,
                'trips_max'  => 15,
            ],

            // ── D: Desert Enthusiast ─────────────────────────────────────────
            'D' => [
                'skill'      => ['advanced', 'advanced', 'expert'],
                'comfort'    => ['basic',    'basic',    'basic'],
                'budget'     => ['budget',   'moderate', 'moderate'],
                'styles'     => [
                    ['désert', 'bivouac', 'aventure extrême', '4x4'],
                    ['sahara', 'bivouac', 'navigation', 'désert'],
                    ['désert', 'expédition', 'stargazing', 'bivouac'],
                ],
                'activities' => [
                    ['Bivouac désertique', 'Navigation GPS', 'Stargazing', 'Randonnée saharienne'],
                    ['Bivouac désertique', 'Exploration 4x4', 'Stargazing', 'Survie désert'],
                    ['Bivouac désertique', 'Navigation GPS', 'Exploration 4x4', 'Photographie désert'],
                ],
                'gear'       => [
                    ['Tente désert légère', 'GPS de randonnée', 'Kit survie', 'Gourde 2L', 'Sac de couchage léger'],
                    ['Tente bivouac désert', 'Boussole + carte', 'Filtre à eau', 'Bâton marche', 'Couverture survie'],
                    ['Tente dôme désert', 'GPS satellite', 'Kit premiers secours', 'Réserve eau 5L', 'Sac de couchage mumie'],
                ],
                'trips_min'  => 10,
                'trips_max'  => 30,
            ],

            // ── E: Glamping Couple ───────────────────────────────────────────
            'E' => [
                'skill'      => ['beginner', 'beginner',  'beginner'],
                'comfort'    => ['glamping', 'glamping',  'glamping'],
                'budget'     => ['premium',  'premium',   'premium'],
                'styles'     => [
                    ['glamping', 'couple', 'détente', 'gastronomie'],
                    ['glamping', 'romantique', 'bien-être', 'côtier'],
                    ['glamping', 'luxe', 'nature', 'spa'],
                ],
                'activities' => [
                    ['Relaxation', 'Baignade', 'Gastronomie en plein air', 'Coucher de soleil'],
                    ['Relaxation', 'Spa nature', 'Gastronomie', 'Promenade romantique'],
                    ['Baignade', 'Gastronomie', 'Coucher de soleil', 'Observation étoiles'],
                ],
                'gear'       => [
                    ['Tente lodge premium', 'Matelas double gonflable', 'Kit cuisine gourmet', 'Mobilier outdoor'],
                    ['Tente bell tent', 'Tapis de sol premium', 'Lanterne LED déco', 'Hamac double'],
                    ['Tente safari', 'Couette outdoor', 'Kit apéro camping', 'Guirlandes solaires'],
                ],
                'trips_min'  => 1,
                'trips_max'  => 8,
            ],

            // ── F: Student Group Member ──────────────────────────────────────
            'F' => [
                'skill'      => ['beginner',     'beginner',     'intermediate'],
                'comfort'    => ['basic',         'basic',        'basic'],
                'budget'     => ['budget',        'budget',       'budget'],
                'styles'     => [
                    ['groupe', 'social', 'découverte', 'budget'],
                    ['groupe', 'jeunesse', 'aventure', 'budget'],
                    ['groupe', 'social', 'nature', 'randonnée'],
                ],
                'activities' => [
                    ['Camping groupe', 'Randonnée facile', 'Soirée feu de camp', 'Jeux collectifs'],
                    ['Camping groupe', 'Baignade', 'Soirée feu de camp', 'Randonnée'],
                    ['Randonnée facile', 'Camping groupe', 'Jeux collectifs', 'Observation nature'],
                ],
                'gear'       => [
                    ['Sac de couchage basique', 'Tapis de sol mousse', 'Sac à dos 20L'],
                    ['Sac de couchage 2 saisons', 'Sac à dos 25L', 'Lampe frontale basique'],
                    ['Tente 2p économique', 'Sac de couchage', 'Gourde', 'Sac à dos 30L'],
                ],
                'trips_min'  => 1,
                'trips_max'  => 5,
            ],
        ];

        $d = $data[$archetype] ?? $data['C'];

        return [
            $d['skill'][$v],
            $d['comfort'][$v],
            $d['budget'][$v],
            $d['styles'][$v],
            $d['activities'][$v],
            $d['gear'][$v],
            rand($d['trips_min'], $d['trips_max']),
        ];
    }
}
