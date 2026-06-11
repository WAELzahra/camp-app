<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds a richer catalogue of UPCOMING (scheduled) events so the AI event
 * recommender has enough variety to personalise across terrain, difficulty,
 * type and price. Generated programmatically and distributed round-robin across
 * the real organizer accounts. Idempotent via a sentinel title.
 *
 * Tags are terrain-aligned (montagne / plage / foret / desert / lac) so the
 * recommendation engine's terrain matching can surface events that fit a
 * camper's preferred_trip_styles.
 */
class AdditionalCampingEventsSeeder extends Seeder
{
    private const SENTINEL = 'Trek Jebel Zaghouan — Sommet & Aqueduc Romain';

    /** [region, terrain-tag, lat, lng, address] */
    private const SPOTS = [
        ['Zaghouan',  'montagne', 36.3700, 10.1420, 'Jebel Zaghouan, Zaghouan, Tunisie'],
        ['Kasserine', 'montagne', 35.2200, 8.6800,  'Jebel Chambi, Kasserine, Tunisie'],
        ['Jendouba',  'foret',    36.7800, 8.6900,  'Forêt de Kroumirie, Aïn Draham, Jendouba'],
        ['Béja',      'foret',    36.7300, 9.1800,  'Parc National Ichkeul, Béja, Tunisie'],
        ['Bizerte',   'plage',    37.2400, 9.8600,  'Cap Angela, Bizerte, Tunisie'],
        ['Nabeul',    'plage',    36.8900, 10.9600, 'Plage de Kélibia, Nabeul, Tunisie'],
        ['Tozeur',    'desert',   33.9200, 8.1300,  'Oasis de Tozeur, Tunisie'],
        ['Kébili',    'desert',   33.7000, 9.0000,  'Erg Oriental, Douz, Kébili'],
        ['Gabès',     'montagne', 33.5400, 9.9700,  'Matmata, Gabès, Tunisie'],
        ['Siliana',   'foret',    36.0800, 9.3700,  'Forêt de Kesra, Siliana, Tunisie'],
        ['Béja',      'lac',      36.8600, 9.2200,  'Barrage Sidi Salem, Béja, Tunisie'],
        ['Kairouan',  'plaine',   35.6800, 10.1000, 'Réserve d\'Oued Zroud, Kairouan'],
    ];

    /** [type, titlePrefix, difficulty|null, basePrice, tagsExtra[]] */
    private const TEMPLATES = [
        ['hiking',  'Trek',              'difficult', 35, ['randonnee', 'trek', 'aventure']],
        ['hiking',  'Randonnée',         'moderate',  20, ['randonnee', 'nature']],
        ['hiking',  'Marche Découverte', 'easy',      12, ['randonnee', 'famille', 'decouverte']],
        ['camping', 'Bivouac',           null,        45, ['bivouac', 'nature', 'etoiles']],
        ['camping', 'Week-end Camping',  null,        30, ['camping', 'groupe', 'social']],
        ['camping', 'Camping Famille',   null,        25, ['camping', 'famille', 'detente']],
    ];

    public function run(): void
    {
        if (DB::table('events')->where('title', self::SENTINEL)->exists()) {
            $this->command?->warn('⚠️  AdditionalCampingEventsSeeder already run — skipping.');
            return;
        }

        $organizerRoleId = DB::table('roles')->where('name', 'organizer')->value('id');
        $organizerIds    = DB::table('users')->where('role_id', $organizerRoleId)->pluck('id')->all();
        if (empty($organizerIds)) {
            $this->command?->warn('⚠️  No organizers — skipping.');
            return;
        }

        $now  = now()->toDateTimeString();
        $rows = [];
        $i    = 0;

        // Upcoming dates spread July–October 2026.
        $startBase = strtotime('2026-07-05');

        foreach (self::SPOTS as $spot) {
            [$region, $terrain, $lat, $lng, $address] = $spot;

            // Two events per spot, different templates → ~24 events.
            foreach ([$i % 6, ($i + 3) % 6] as $tIdx) {
                [$type, $prefix, $difficulty, $basePrice, $tagsExtra] = self::TEMPLATES[$tIdx];

                $start    = date('Y-m-d', $startBase + ($i * 6 * 86400));
                $nights   = $type === 'hiking' ? 0 : rand(1, 3);
                $end      = date('Y-m-d', strtotime($start) + max($nights, 1) * 86400);
                $capacity = rand(10, 30);
                $booked   = rand(0, (int) ($capacity * 0.6));
                $price    = $type === 'hiking' && $tIdx === 2 && $i % 4 === 0 ? 0.00 : (float) ($basePrice + rand(-5, 15));

                $tags = array_values(array_unique(array_merge([$terrain, $region], $tagsExtra)));

                $rows[] = [
                    'group_id'         => $organizerIds[$i % count($organizerIds)],
                    'title'            => ($i === 0 ? self::SENTINEL : "{$prefix} {$region} — " . ucfirst($terrain)),
                    'description'      => "Sortie {$type} organisée dans la région de {$region} ({$terrain}). "
                        . 'Encadrement par un organisateur local, ambiance conviviale et découverte de la nature tunisienne.',
                    'event_type'       => $type,
                    'start_date'       => $start,
                    'end_date'         => $end,
                    'capacity'         => $capacity,
                    'price'            => $price,
                    'remaining_spots'  => max($capacity - $booked, 0),
                    'difficulty'       => $difficulty,
                    'hiking_duration'  => $type === 'hiking' ? (float) rand(3, 8) : null,
                    'elevation_gain'   => $type === 'hiking' ? rand(150, 900) : null,
                    'camping_duration' => $type === 'camping' ? $nights : null,
                    'camping_gear'     => $type === 'camping' ? 'Tente et matériel de base fournis sur demande' : null,
                    'is_group_travel'  => false,
                    'latitude'         => $lat,
                    'longitude'        => $lng,
                    'address'          => $address,
                    'tags'             => json_encode($tags, JSON_UNESCAPED_UNICODE),
                    'is_active'        => true,
                    'status'           => 'scheduled',
                    'views_count'      => rand(20, 600),
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];

                $i++;
            }
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('events')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($rows) . ' additional (scheduled) events inserted.');
    }
}
