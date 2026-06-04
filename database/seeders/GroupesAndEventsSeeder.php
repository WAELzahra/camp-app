<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupesAndEventsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('followers_groupes')->truncate();
        DB::table('events')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Load groupe users (user_id keyed by email) ────────────────────────
        $groupeRoleId  = DB::table('roles')->where('name', 'groupe')->value('id');
        $campeurRoleId = DB::table('roles')->where('name', 'campeur')->value('id');

        $groupeUsers = DB::table('users')
            ->where('role_id', $groupeRoleId)
            ->pluck('id', 'email')
            ->all();

        // ── Load profile_groupes (profile_groupes.id keyed by user_id) ────────
        $pgByUserId = DB::table('profile_groupes as pg')
            ->join('profiles as p', 'p.id', '=', 'pg.profile_id')
            ->pluck('pg.id', 'p.user_id')
            ->all();

        // ── Insert events ─────────────────────────────────────────────────────
        $eventDefs = $this->eventDefinitions();
        $eventRows = [];

        foreach ($eventDefs as $def) {
            $uid = $groupeUsers[$def['email']] ?? null;
            if (! $uid) continue;

            foreach ($def['events'] as $e) {
                $eventRows[] = array_merge([
                    'group_id'   => $uid,
                    'is_active'  => $e['status'] === 'scheduled' || $e['status'] === 'ongoing',
                    'views_count'=> rand(20, 400),
                    'tags'       => json_encode($e['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                    // nullable fields default
                    'camping_duration'         => null,
                    'camping_gear'             => null,
                    'is_group_travel'          => false,
                    'departure_city'           => null,
                    'arrival_city'             => null,
                    'departure_time'           => null,
                    'estimated_arrival_time'   => null,
                    'bus_company'              => null,
                    'bus_number'               => null,
                    'city_stops'               => null,
                    'hiking_duration'          => null,
                    'elevation_gain'           => null,
                ], $e, ['tags' => json_encode($e['tags'] ?? [], JSON_UNESCAPED_UNICODE)]);
            }
        }

        foreach (array_chunk($eventRows, 50) as $chunk) {
            DB::table('events')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($eventRows) . ' events inserted.');

        // ── Insert followers_groupes ──────────────────────────────────────────
        $campeurs = DB::table('users')
            ->where('role_id', $campeurRoleId)
            ->select('id', 'preferences')
            ->get();

        // group email → profile_groupes.id mapping
        $pgIdByEmail = [];
        foreach ($groupeUsers as $email => $uid) {
            if (isset($pgByUserId[$uid])) {
                $pgIdByEmail[$email] = $pgByUserId[$uid];
            }
        }

        // archetype → list of groupe emails they should follow
        $archetypeGroups = $this->archetypeGroupMap();

        $followerRows = [];
        $seen         = [];

        foreach ($campeurs as $c) {
            $prefs     = json_decode($c->preferences, true);
            $archetype = $prefs['archetype'] ?? 'C';
            $eligible  = $archetypeGroups[$archetype] ?? $archetypeGroups['C'];

            // pick 1–4 groups deterministically
            $count  = ($c->id % 4) + 1;
            $picked = array_slice($eligible, ($c->id % max(1, count($eligible) - $count)), $count);
            if (count($picked) < $count) {
                $picked = array_slice($eligible, 0, $count);
            }

            foreach ($picked as $email) {
                $pgId = $pgIdByEmail[$email] ?? null;
                if (! $pgId) continue;

                $key = $c->id . '_' . $pgId;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                $followerRows[] = [
                    'user_id'    => $c->id,
                    'groupe_id'  => $pgId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($followerRows, 50) as $chunk) {
            DB::table('followers_groupes')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($followerRows) . ' followers_groupes records inserted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Archetype → groupe emails they should follow
    // ─────────────────────────────────────────────────────────────────────────
    private function archetypeGroupMap(): array
    {
        return [
            'A' => [
                'club.aventure.tunis@gmail.com',
                'randonneurs.nord@gmail.com',
                'mountain.climbers.beja@gmail.com',
                'mer.montagne.bizerte@gmail.com',
                'wilderness.tunis@gmail.com',
                'aventuriers.kef@gmail.com',
            ],
            'B' => [
                'camp.family.nabeul@gmail.com',
                'nature.lovers.sousse@gmail.com',
                'coastal.explorers.mahdia@gmail.com',
                'jeunesse.nature.monastir@gmail.com',
                'randonneurs.kairouan@gmail.com',
            ],
            'C' => [
                'club.aventure.tunis@gmail.com',
                'randonneurs.nord@gmail.com',
                'outdoor.club.siliana@gmail.com',
                'ecotourisme.jendouba@gmail.com',
                'aventuriers.kef@gmail.com',
                'wilderness.tunis@gmail.com',
            ],
            'D' => [
                'desert.explorers.tozeur@gmail.com',
                'trek.sud.tunisie@gmail.com',
                'bivouac.sahara@gmail.com',
                'sahara.trek.gabes@gmail.com',
            ],
            'E' => [
                'camp.family.nabeul@gmail.com',
                'coastal.explorers.mahdia@gmail.com',
                'nature.lovers.sousse@gmail.com',
                'jeunesse.nature.monastir@gmail.com',
            ],
            'F' => [
                'jeunesse.nature.monastir@gmail.com',
                'club.aventure.tunis@gmail.com',
                'nature.lovers.sousse@gmail.com',
                'aventure.sfax@gmail.com',
                'randonneurs.nord@gmail.com',
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event definitions — 2–4 events per groupe
    // Dates relative to 2026-05-10 (today)
    // ─────────────────────────────────────────────────────────────────────────
    private function eventDefinitions(): array
    {
        return [

            // ── Club Aventure Tunis ───────────────────────────────────────────
            [
                'email' => 'club.aventure.tunis@gmail.com',
                'events' => [
                    [
                        'title'           => 'Randonnée Jebel Ressas',
                        'description'     => 'Ascension du Jebel Ressas au sud de Tunis. Vue panoramique sur le golfe de Tunis et les plaines du Cap Bon. Randonnée accessible aux débutants motivés.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2025-11-08',
                        'end_date'        => '2025-11-08',
                        'capacity'        => 30,
                        'price'           => 15.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 4.5,
                        'elevation_gain'  => 420,
                        'latitude'        => 36.6167,
                        'longitude'       => 10.2833,
                        'address'         => 'Jebel Ressas, Ben Arous, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['randonnee', 'montagne', 'tunis', 'debutant'],
                    ],
                    [
                        'title'           => 'Trek Forêt de Bargou',
                        'description'     => 'Trek de deux jours à travers la magnifique forêt de Bargou. Nuit en camping sous les étoiles. Faune et flore exceptionnelles du nord tunisien.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-02-14',
                        'end_date'        => '2026-02-15',
                        'capacity'        => 25,
                        'price'           => 45.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'moderate',
                        'hiking_duration' => 12.0,
                        'elevation_gain'  => 680,
                        'latitude'        => 36.0667,
                        'longitude'       => 9.1000,
                        'address'         => 'Forêt de Bargou, Siliana, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['trek', 'foret', 'camping', 'siliana'],
                    ],
                    [
                        'title'           => 'Camping Aïn Draham — Fête de printemps',
                        'description'     => 'Weekend camping dans les forêts de chênes-liège d\'Aïn Draham. Randonnées quotidiennes, feu de camp et découverte de la biodiversité de la Kroumirie.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-06-13',
                        'end_date'        => '2026-06-15',
                        'capacity'        => 35,
                        'price'           => 60.00,
                        'remaining_spots' => 12,
                        'difficulty'      => 'easy',
                        'latitude'        => 36.7769,
                        'longitude'       => 8.6881,
                        'address'         => 'Aïn Draham, Jendouba, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'foret', 'kroumirie', 'printemps'],
                    ],
                ],
            ],

            // ── Les Randonneurs du Nord ───────────────────────────────────────
            [
                'email' => 'randonneurs.nord@gmail.com',
                'events' => [
                    [
                        'title'           => 'Cap Blanc & Falaises de Bizerte',
                        'description'     => 'Randonnée côtière au point le plus septentrional de l\'Afrique. Falaises spectaculaires, panorama sur la mer Méditerranée et visite du phare.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2025-10-18',
                        'end_date'        => '2025-10-18',
                        'capacity'        => 25,
                        'price'           => 10.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 3.0,
                        'elevation_gain'  => 80,
                        'latitude'        => 37.3417,
                        'longitude'       => 9.8000,
                        'address'         => 'Cap Blanc, Bizerte, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['randonnee', 'cotier', 'bizerte', 'cap'],
                    ],
                    [
                        'title'           => 'Trek Parc National Ichkeul',
                        'description'     => 'Randonnée dans le parc national d\'Ichkeul, site UNESCO. Observation des flamants roses, canards et autres oiseaux migrateurs sur le lac.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-07-04',
                        'end_date'        => '2026-07-04',
                        'capacity'        => 30,
                        'price'           => 18.00,
                        'remaining_spots' => 8,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 5.0,
                        'elevation_gain'  => 150,
                        'latitude'        => 37.1333,
                        'longitude'       => 9.6667,
                        'address'         => 'Parc National Ichkeul, Bizerte, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['randonnee', 'oiseaux', 'ichkeul', 'unesco'],
                    ],
                    [
                        'title'           => 'Camping Forêt de Mogod',
                        'description'     => 'Nuit en camping au cœur de la forêt de Mogod. Randonnée matinale, cueillette de champignons et découverte de la flore endémique du nord tunisien.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-08-22',
                        'end_date'        => '2026-08-23',
                        'capacity'        => 20,
                        'price'           => 35.00,
                        'remaining_spots' => 5,
                        'difficulty'      => 'moderate',
                        'latitude'        => 37.0833,
                        'longitude'       => 9.4167,
                        'address'         => 'Forêt de Mogod, Béja, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'foret', 'mogod', 'nature'],
                    ],
                ],
            ],

            // ── Désert Explorers Tozeur ───────────────────────────────────────
            [
                'email' => 'desert.explorers.tozeur@gmail.com',
                'events' => [
                    [
                        'title'           => 'Bivouac Dunes de Douz',
                        'description'     => 'Nuit en bivouac au milieu des dunes de sable de Douz, la porte du désert. Coucher et lever de soleil à couper le souffle, repas berbères sous les étoiles.',
                        'event_type'      => 'camping',
                        'start_date'      => '2025-12-05',
                        'end_date'        => '2025-12-06',
                        'capacity'        => 20,
                        'price'           => 80.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'moderate',
                        'latitude'        => 33.4556,
                        'longitude'       => 9.0231,
                        'address'         => 'Dunes de Douz, Kébili, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['bivouac', 'desert', 'dunes', 'douz', 'sahara'],
                    ],
                    [
                        'title'           => 'Expédition Chott el-Jérid',
                        'description'     => 'Traversée du plus grand lac salé d\'Afrique du Nord. Paysages lunaires, mirages et observation de la faune adaptée aux conditions extrêmes du désert.',
                        'event_type'      => 'voyage',
                        'start_date'      => '2026-01-16',
                        'end_date'        => '2026-01-18',
                        'capacity'        => 15,
                        'price'           => 120.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'difficult',
                        'is_group_travel' => true,
                        'departure_city'  => 'Tozeur',
                        'arrival_city'    => 'Kébili',
                        'latitude'        => 33.7167,
                        'longitude'       => 8.4333,
                        'address'         => 'Chott el-Jérid, Tozeur, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['expedition', 'chott', 'desert', 'extreme'],
                    ],
                    [
                        'title'           => 'Trek Canyons de Tamerza',
                        'description'     => 'Trek dans les gorges et canyons de Tamerza, la plus grande oasis de montagne de Tunisie. Cascades, ruines et paysages à couper le souffle.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-10-09',
                        'end_date'        => '2026-10-10',
                        'capacity'        => 18,
                        'price'           => 65.00,
                        'remaining_spots' => 6,
                        'difficulty'      => 'difficult',
                        'hiking_duration' => 8.0,
                        'elevation_gain'  => 350,
                        'latitude'        => 34.3833,
                        'longitude'       => 7.9333,
                        'address'         => 'Tamerza, Gafsa, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['canyon', 'oasis', 'tamerza', 'desert'],
                    ],
                    [
                        'title'           => 'Nuits Étoilées à Ksar Ghilane',
                        'description'     => 'Camp de base à Ksar Ghilane, oasis au milieu du Grand Erg Oriental. Baignade dans les sources thermales naturelles, quad sur les dunes et stargazing.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-11-27',
                        'end_date'        => '2026-11-29',
                        'capacity'        => 22,
                        'price'           => 95.00,
                        'remaining_spots' => 10,
                        'difficulty'      => 'moderate',
                        'latitude'        => 32.9833,
                        'longitude'       => 9.6333,
                        'address'         => 'Ksar Ghilane, Gabès, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'oasis', 'sahara', 'stargazing'],
                    ],
                ],
            ],

            // ── Mer et Montagne Bizerte ───────────────────────────────────────
            [
                'email' => 'mer.montagne.bizerte@gmail.com',
                'events' => [
                    [
                        'title'           => 'Randonnée Jebel Nadour',
                        'description'     => 'Montée au Jebel Nadour avec vue sur la mer Méditerranée, le lac de Bizerte et les collines environnantes. Pique-nique au sommet inclus.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2025-09-20',
                        'end_date'        => '2025-09-20',
                        'capacity'        => 28,
                        'price'           => 12.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'moderate',
                        'hiking_duration' => 5.0,
                        'elevation_gain'  => 320,
                        'latitude'        => 37.2667,
                        'longitude'       => 9.8333,
                        'address'         => 'Jebel Nadour, Bizerte, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['randonnee', 'montagne', 'bizerte', 'panorama'],
                    ],
                    [
                        'title'           => 'Camping Plage Sounine Bizerte — Annulé',
                        'description'     => 'Weekend camping sur la plage de Sounine. Annulé en raison de conditions météorologiques défavorables.',
                        'event_type'      => 'camping',
                        'start_date'      => '2025-08-09',
                        'end_date'        => '2025-08-10',
                        'capacity'        => 30,
                        'price'           => 25.00,
                        'remaining_spots' => 30,
                        'difficulty'      => 'easy',
                        'latitude'        => 37.2500,
                        'longitude'       => 9.9500,
                        'address'         => 'Plage Sounine, Bizerte, Tunisie',
                        'status'          => 'canceled',
                        'tags'            => ['camping', 'plage', 'bizerte', 'annule'],
                    ],
                    [
                        'title'           => 'Exploration Côte Corniche Bizerte',
                        'description'     => 'Randonnée côtière sur la corniche de Bizerte. Baignade dans les criques cachées, observation de la faune marine et déjeuner dans un restaurant local.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-07-18',
                        'end_date'        => '2026-07-18',
                        'capacity'        => 25,
                        'price'           => 20.00,
                        'remaining_spots' => 9,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 4.0,
                        'elevation_gain'  => 60,
                        'latitude'        => 37.2744,
                        'longitude'       => 9.8739,
                        'address'         => 'Corniche Bizerte, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['randonnee', 'cotier', 'baignade', 'bizerte'],
                    ],
                ],
            ],

            // ── Camp Family Nabeul ────────────────────────────────────────────
            [
                'email' => 'camp.family.nabeul@gmail.com',
                'events' => [
                    [
                        'title'           => 'Camping Plage Hammamet Famille',
                        'description'     => 'Weekend camping en famille sur la plage de Hammamet. Activités pour enfants, baignade surveillée, jeux de plage et barbecue en soirée.',
                        'event_type'      => 'camping',
                        'start_date'      => '2025-08-01',
                        'end_date'        => '2025-08-03',
                        'capacity'        => 50,
                        'price'           => 30.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'latitude'        => 36.3986,
                        'longitude'       => 10.5681,
                        'address'         => 'Plage Hammamet, Nabeul, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['famille', 'plage', 'camping', 'enfants', 'baignade'],
                    ],
                    [
                        'title'           => 'Balade Cap Bon en Famille',
                        'description'     => 'Découverte de la péninsule de Cap Bon avec les familles. Visite du parc naturel, dégustation de vins locaux pour les adultes et jeux nature pour les enfants.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2025-11-22',
                        'end_date'        => '2025-11-22',
                        'capacity'        => 40,
                        'price'           => 20.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 3.0,
                        'elevation_gain'  => 50,
                        'latitude'        => 36.8333,
                        'longitude'       => 10.5833,
                        'address'         => 'Cap Bon, Nabeul, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['famille', 'cap bon', 'nature', 'enfants'],
                    ],
                    [
                        'title'           => 'Camping Estival Kelibia Famille',
                        'description'     => 'Grand camping familial à Kelibia près du fort punique. Plage de sable blanc, eaux cristallines et programme d\'activités variées pour petits et grands.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-07-25',
                        'end_date'        => '2026-07-27',
                        'capacity'        => 60,
                        'price'           => 40.00,
                        'remaining_spots' => 18,
                        'difficulty'      => 'easy',
                        'latitude'        => 36.8500,
                        'longitude'       => 11.1000,
                        'address'         => 'Kelibia, Nabeul, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['famille', 'plage', 'kelibia', 'ete'],
                    ],
                ],
            ],

            // ── Aventure Sfax ─────────────────────────────────────────────────
            [
                'email' => 'aventure.sfax@gmail.com',
                'events' => [
                    [
                        'title'           => 'Découverte Îles Kerkennah',
                        'description'     => 'Voyage en ferry vers les îles Kerkennah. Randonnée à vélo sur les îles plates, snorkeling, pêche traditionnelle et gastronomie de fruits de mer.',
                        'event_type'      => 'voyage',
                        'start_date'      => '2025-10-03',
                        'end_date'        => '2025-10-04',
                        'capacity'        => 25,
                        'price'           => 55.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'is_group_travel' => true,
                        'departure_city'  => 'Sfax',
                        'arrival_city'    => 'Kerkennah',
                        'latitude'        => 34.7167,
                        'longitude'       => 11.2333,
                        'address'         => 'Îles Kerkennah, Sfax, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['voyage', 'iles', 'kerkennah', 'mer', 'peche'],
                    ],
                    [
                        'title'           => 'Camping Nature Chébika Sfax',
                        'description'     => 'Sortie camping dans les environs naturels de Sfax. Randonnée dans les oliveraies et les zones naturelles, nuit sous les étoiles et cuisine locale.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-09-05',
                        'end_date'        => '2026-09-06',
                        'capacity'        => 30,
                        'price'           => 35.00,
                        'remaining_spots' => 11,
                        'difficulty'      => 'moderate',
                        'latitude'        => 34.7800,
                        'longitude'       => 10.6400,
                        'address'         => 'Environs de Sfax, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'nature', 'sfax', 'oliveraies'],
                    ],
                ],
            ],

            // ── Trek Sud Tunisie ──────────────────────────────────────────────
            [
                'email' => 'trek.sud.tunisie@gmail.com',
                'events' => [
                    [
                        'title'           => 'Circuit Ksour du Sud',
                        'description'     => 'Expédition de 3 jours à la découverte des ksour berbères : Ksar Ouled Soltane, Ksar Hadada, Chenini. Voyage en 4x4 à travers le paysage désertique.',
                        'event_type'      => 'voyage',
                        'start_date'      => '2026-01-09',
                        'end_date'        => '2026-01-11',
                        'capacity'        => 16,
                        'price'           => 150.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'difficult',
                        'is_group_travel' => true,
                        'departure_city'  => 'Gafsa',
                        'arrival_city'    => 'Tataouine',
                        'latitude'        => 32.9167,
                        'longitude'       => 10.4500,
                        'address'         => 'Ksour, Tataouine, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['ksour', 'berbere', 'desert', '4x4', 'patrimoine'],
                    ],
                    [
                        'title'           => 'Trek Douz–Zaafrane',
                        'description'     => 'Trek de 2 jours en dromadaire entre Douz et Zaafrane. Bivouac au cœur du Grand Erg Oriental, lever de soleil sur les dunes et repas traditionnels.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-03-06',
                        'end_date'        => '2026-03-07',
                        'capacity'        => 14,
                        'price'           => 110.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'difficult',
                        'hiking_duration' => 16.0,
                        'elevation_gain'  => 0,
                        'latitude'        => 33.4556,
                        'longitude'       => 9.0231,
                        'address'         => 'Douz, Kébili, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['dromadaire', 'dunes', 'bivouac', 'sahara'],
                    ],
                    [
                        'title'           => 'Expédition Grand Erg Oriental',
                        'description'     => 'Expédition extrême de 4 jours dans le Grand Erg Oriental. Bivouacs successifs, navigation GPS, survie en zone aride — pour aventuriers expérimentés uniquement.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-11-06',
                        'end_date'        => '2026-11-09',
                        'capacity'        => 10,
                        'price'           => 180.00,
                        'remaining_spots' => 3,
                        'difficulty'      => 'expert',
                        'latitude'        => 33.8000,
                        'longitude'       => 9.5000,
                        'address'         => 'Grand Erg Oriental, Kébili, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['expedition', 'expert', 'desert', 'bivouac', 'navigation'],
                    ],
                ],
            ],

            // ── Nature Lovers Sousse ──────────────────────────────────────────
            [
                'email' => 'nature.lovers.sousse@gmail.com',
                'events' => [
                    [
                        'title'           => 'Balade Côtière Sousse–Monastir',
                        'description'     => 'Randonnée côtière de Sousse à Monastir le long des plages. Baignade dans les criques, observation des oiseaux marins et déjeuner pique-nique.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2025-09-27',
                        'end_date'        => '2025-09-27',
                        'capacity'        => 35,
                        'price'           => 0.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 4.0,
                        'elevation_gain'  => 20,
                        'latitude'        => 35.8333,
                        'longitude'       => 10.6333,
                        'address'         => 'Côte Sousse, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['cotier', 'baignade', 'sousse', 'monastir', 'gratuit'],
                    ],
                    [
                        'title'           => 'Camping Plage Boujaffar',
                        'description'     => 'Nuit en camping sur la plage Boujaffar de Sousse. Activités aquatiques, feu de camp et soirée musicale au bord de la mer.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-06-20',
                        'end_date'        => '2026-06-21',
                        'capacity'        => 40,
                        'price'           => 25.00,
                        'remaining_spots' => 14,
                        'difficulty'      => 'easy',
                        'latitude'        => 35.8167,
                        'longitude'       => 10.6333,
                        'address'         => 'Plage Boujaffar, Sousse, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'plage', 'sousse', 'soiree'],
                    ],
                    [
                        'title'           => 'Sortie Nature Parc Enfidha',
                        'description'     => 'Découverte de la zone naturelle d\'Enfidha. Observation des flamants roses, randonnée dans les zones humides et atelier photographie nature.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-03-28',
                        'end_date'        => '2026-03-28',
                        'capacity'        => 28,
                        'price'           => 15.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 3.5,
                        'elevation_gain'  => 10,
                        'latitude'        => 36.1333,
                        'longitude'       => 10.3833,
                        'address'         => 'Enfidha, Sousse, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['nature', 'oiseaux', 'flamants', 'enfidha'],
                    ],
                ],
            ],

            // ── Mountain Climbers Béja ────────────────────────────────────────
            [
                'email' => 'mountain.climbers.beja@gmail.com',
                'events' => [
                    [
                        'title'           => 'Ascension Jebel Ghorra',
                        'description'     => 'Randonnée exigeante jusqu\'au sommet du Jebel Ghorra (1014m), point culminant de Béja. Vue à 360° sur la Kroumirie et la vallée de la Medjerda.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2025-12-13',
                        'end_date'        => '2025-12-13',
                        'capacity'        => 20,
                        'price'           => 20.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'difficult',
                        'hiking_duration' => 7.0,
                        'elevation_gain'  => 750,
                        'latitude'        => 36.7333,
                        'longitude'       => 9.0167,
                        'address'         => 'Jebel Ghorra, Béja, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['montagne', 'ascension', 'beja', 'sommet'],
                    ],
                    [
                        'title'           => 'Trek Kroumirie 3 Jours',
                        'description'     => 'Grand trek de 3 jours à travers la chaîne de la Kroumirie. Forêts de chênes-liège et pins, rivières, villages berbères et faune sauvage abondante.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-09-18',
                        'end_date'        => '2026-09-20',
                        'capacity'        => 16,
                        'price'           => 75.00,
                        'remaining_spots' => 5,
                        'difficulty'      => 'difficult',
                        'hiking_duration' => 24.0,
                        'elevation_gain'  => 1200,
                        'latitude'        => 36.8000,
                        'longitude'       => 8.8500,
                        'address'         => 'Kroumirie, Béja, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['trek', 'kroumirie', 'multi-jours', 'montagne'],
                    ],
                    [
                        'title'           => 'Camping Montagne Aïn Draham',
                        'description'     => 'Weekend camping en altitude dans les forêts d\'Aïn Draham. Nuits fraîches, randonnées matinales et soirées autour du feu de camp.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-08-08',
                        'end_date'        => '2026-08-09',
                        'capacity'        => 24,
                        'price'           => 45.00,
                        'remaining_spots' => 8,
                        'difficulty'      => 'moderate',
                        'latitude'        => 36.7769,
                        'longitude'       => 8.6881,
                        'address'         => 'Aïn Draham, Jendouba, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'montagne', 'ain draham', 'altitude'],
                    ],
                ],
            ],

            // ── Bivouac Sahara Club ───────────────────────────────────────────
            [
                'email' => 'bivouac.sahara@gmail.com',
                'events' => [
                    [
                        'title'           => 'Nuit Étoilée Kébili',
                        'description'     => 'Bivouac nocturne aux portes du désert de Kébili. Session d\'astronomie guidée, identification des constellations et contemplation du ciel saharien sans pollution lumineuse.',
                        'event_type'      => 'camping',
                        'start_date'      => '2025-11-21',
                        'end_date'        => '2025-11-21',
                        'capacity'        => 18,
                        'price'           => 50.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'moderate',
                        'latitude'        => 33.7054,
                        'longitude'       => 8.9725,
                        'address'         => 'Kébili, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['bivouac', 'stargazing', 'kebili', 'nuit'],
                    ],
                    [
                        'title'           => 'Bivouac Oasis de Nefta',
                        'description'     => 'Nuit en bivouac dans la corbeille de Nefta, oasis mythique de Tunisie. Sources naturelles, palmiers millénaires et silence du désert.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-02-27',
                        'end_date'        => '2026-02-28',
                        'capacity'        => 20,
                        'price'           => 70.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'moderate',
                        'latitude'        => 33.8731,
                        'longitude'       => 7.8775,
                        'address'         => 'Nefta, Tozeur, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['bivouac', 'oasis', 'nefta', 'sahara'],
                    ],
                    [
                        'title'           => 'Stargazing Chott el-Jérid Nuit',
                        'description'     => 'Nuit spéciale astronomie sur la surface lunaire du Chott el-Jérid. Télescopes professionnels, guide astronome et dîner berbère au milieu du lac salé.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-10-23',
                        'end_date'        => '2026-10-23',
                        'capacity'        => 15,
                        'price'           => 85.00,
                        'remaining_spots' => 7,
                        'difficulty'      => 'difficult',
                        'latitude'        => 33.7167,
                        'longitude'       => 8.4333,
                        'address'         => 'Chott el-Jérid, Tozeur, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['stargazing', 'astronomie', 'chott', 'desert'],
                    ],
                ],
            ],

            // ── Randonneurs Kairouan ──────────────────────────────────────────
            [
                'email' => 'randonneurs.kairouan@gmail.com',
                'events' => [
                    [
                        'title'           => 'Balade Sebkhet Kelbia',
                        'description'     => 'Randonnée autour de la Sebkha de Kelbia en période hivernale. Observation des milliers de flamants roses et autres oiseaux migrateurs qui hivernent ici.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-01-24',
                        'end_date'        => '2026-01-24',
                        'capacity'        => 30,
                        'price'           => 0.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 3.0,
                        'elevation_gain'  => 5,
                        'latitude'        => 36.0833,
                        'longitude'       => 9.7833,
                        'address'         => 'Sebkhet Kelbia, Kairouan, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['oiseaux', 'flamants', 'kelbia', 'nature', 'gratuit'],
                    ],
                    [
                        'title'           => 'Voyage Patrimoine Kairouan',
                        'description'     => 'Sortie culturelle et naturelle autour de Kairouan. Visite des aghlabides, randonnée dans les oliveraies et déjeuner dans un restaurant typique.',
                        'event_type'      => 'voyage',
                        'start_date'      => '2026-06-06',
                        'end_date'        => '2026-06-06',
                        'capacity'        => 35,
                        'price'           => 25.00,
                        'remaining_spots' => 12,
                        'difficulty'      => 'easy',
                        'is_group_travel' => true,
                        'departure_city'  => 'Kairouan',
                        'arrival_city'    => 'Kairouan',
                        'latitude'        => 35.6781,
                        'longitude'       => 10.0963,
                        'address'         => 'Kairouan, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['patrimoine', 'culture', 'kairouan', 'histoire'],
                    ],
                ],
            ],

            // ── Coastal Explorers Mahdia ──────────────────────────────────────
            [
                'email' => 'coastal.explorers.mahdia@gmail.com',
                'events' => [
                    [
                        'title'           => 'Plongée Côte Mahdia',
                        'description'     => 'Sortie plongée et snorkeling sur la côte de Mahdia. Épaves et fonds marins riches en faune méditerranéenne. Initiation pour débutants disponible.',
                        'event_type'      => 'voyage',
                        'start_date'      => '2025-09-13',
                        'end_date'        => '2025-09-13',
                        'capacity'        => 20,
                        'price'           => 40.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'latitude'        => 35.5000,
                        'longitude'       => 11.0667,
                        'address'         => 'Port de Mahdia, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['plongee', 'snorkeling', 'mer', 'mahdia'],
                    ],
                    [
                        'title'           => 'Camping Plage Sidi Salem Mahdia',
                        'description'     => 'Weekend camping sur la belle plage de Sidi Salem. Kayak de mer, snorkeling, pêche et coucher de soleil sur la Méditerranée.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-07-11',
                        'end_date'        => '2026-07-12',
                        'capacity'        => 30,
                        'price'           => 35.00,
                        'remaining_spots' => 10,
                        'difficulty'      => 'easy',
                        'latitude'        => 35.4500,
                        'longitude'       => 11.0333,
                        'address'         => 'Plage Sidi Salem, Mahdia, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'plage', 'mahdia', 'kayak'],
                    ],
                    [
                        'title'           => 'Exploration Îles Kerkennah depuis Mahdia',
                        'description'     => 'Voyage d\'une journée vers les îles Kerkennah depuis Mahdia. Ferry, vélos sur les îles, déjeuner de poissons frais et retour en soirée.',
                        'event_type'      => 'voyage',
                        'start_date'      => '2026-04-04',
                        'end_date'        => '2026-04-04',
                        'capacity'        => 25,
                        'price'           => 55.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'is_group_travel' => true,
                        'departure_city'  => 'Mahdia',
                        'arrival_city'    => 'Kerkennah',
                        'latitude'        => 34.7167,
                        'longitude'       => 11.2333,
                        'address'         => 'Îles Kerkennah, Sfax, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['iles', 'kerkennah', 'voyage', 'mer'],
                    ],
                ],
            ],

            // ── Jeunesse Nature Monastir ──────────────────────────────────────
            [
                'email' => 'jeunesse.nature.monastir@gmail.com',
                'events' => [
                    [
                        'title'           => 'Camping Jeunesse Monastir',
                        'description'     => 'Weekend camping pour jeunes sur la côte de Monastir. Activités team building, volleyball de plage, ateliers survie et soirée culturelle.',
                        'event_type'      => 'camping',
                        'start_date'      => '2025-10-24',
                        'end_date'        => '2025-10-25',
                        'capacity'        => 45,
                        'price'           => 20.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'latitude'        => 35.7642,
                        'longitude'       => 10.8113,
                        'address'         => 'Monastir, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['jeunesse', 'camping', 'monastir', 'teambuilding'],
                    ],
                    [
                        'title'           => 'Sortie Découverte Sfax',
                        'description'     => 'Voyage d\'une journée à Sfax pour découvrir la vieille médina, le musée de Sfax et les traditions de la région. Transport, guide et déjeuner inclus.',
                        'event_type'      => 'voyage',
                        'start_date'      => '2026-06-27',
                        'end_date'        => '2026-06-27',
                        'capacity'        => 40,
                        'price'           => 30.00,
                        'remaining_spots' => 16,
                        'difficulty'      => 'easy',
                        'is_group_travel' => true,
                        'departure_city'  => 'Monastir',
                        'arrival_city'    => 'Sfax',
                        'latitude'        => 34.7406,
                        'longitude'       => 10.7603,
                        'address'         => 'Médina de Sfax, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['voyage', 'culture', 'sfax', 'decouverte'],
                    ],
                    [
                        'title'           => 'Randonnée Familiale Sebkhet Monastir',
                        'description'     => 'Randonnée nature autour de la Sebkhet de Monastir. Idéale pour familles avec enfants, observation de la faune locale et pique-nique en plein air.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-03-14',
                        'end_date'        => '2026-03-14',
                        'capacity'        => 50,
                        'price'           => 0.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 2.5,
                        'elevation_gain'  => 5,
                        'latitude'        => 35.7833,
                        'longitude'       => 10.8333,
                        'address'         => 'Sebkhet Monastir, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['famille', 'enfants', 'nature', 'monastir', 'gratuit'],
                    ],
                ],
            ],

            // ── Sahara Trek Gabès ─────────────────────────────────────────────
            [
                'email' => 'sahara.trek.gabes@gmail.com',
                'events' => [
                    [
                        'title'           => 'Trek Oasis de Gabès',
                        'description'     => 'Randonnée dans l\'unique oasis maritime du monde à Gabès. Palmiers les pieds dans l\'eau, marché traditionnel et rencontre avec les artisans locaux.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2025-12-20',
                        'end_date'        => '2025-12-20',
                        'capacity'        => 25,
                        'price'           => 15.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 3.0,
                        'elevation_gain'  => 10,
                        'latitude'        => 33.8833,
                        'longitude'       => 10.1167,
                        'address'         => 'Oasis de Gabès, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['oasis', 'gabes', 'palmiers', 'culture'],
                    ],
                    [
                        'title'           => 'Expédition Matmata Troglodytes — Annulée',
                        'description'     => 'Bivouac dans les maisons troglodytes de Matmata. Annulée en raison d\'une alerte météo.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-02-07',
                        'end_date'        => '2026-02-08',
                        'capacity'        => 18,
                        'price'           => 75.00,
                        'remaining_spots' => 18,
                        'difficulty'      => 'moderate',
                        'latitude'        => 33.5444,
                        'longitude'       => 9.9722,
                        'address'         => 'Matmata, Gabès, Tunisie',
                        'status'          => 'canceled',
                        'tags'            => ['matmata', 'troglodyte', 'desert', 'annule'],
                    ],
                    [
                        'title'           => 'Bivouac Douz à Gabès',
                        'description'     => 'Traversée en 4 jours de Douz à Gabès à travers le désert. Bivouacs successifs, dunes, oasis et paysages changeants du Sahara tunisien.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-10-30',
                        'end_date'        => '2026-11-02',
                        'capacity'        => 12,
                        'price'           => 160.00,
                        'remaining_spots' => 4,
                        'difficulty'      => 'difficult',
                        'latitude'        => 33.4556,
                        'longitude'       => 9.0231,
                        'address'         => 'Douz–Gabès, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['bivouac', 'traversee', 'desert', '4x4'],
                    ],
                ],
            ],

            // ── Aventuriers du Kef ────────────────────────────────────────────
            [
                'email' => 'aventuriers.kef@gmail.com',
                'events' => [
                    [
                        'title'           => 'Ascension Jebel Dyr',
                        'description'     => 'Randonnée vers le sommet du Jebel Dyr (1084m), point culminant du Tell tunisien. Panorama exceptionnel sur l\'Algérie et les montagnes du nord-ouest.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2025-11-15',
                        'end_date'        => '2025-11-15',
                        'capacity'        => 18,
                        'price'           => 18.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'difficult',
                        'hiking_duration' => 8.0,
                        'elevation_gain'  => 820,
                        'latitude'        => 36.1667,
                        'longitude'       => 8.5333,
                        'address'         => 'Jebel Dyr, Le Kef, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['montagne', 'sommet', 'kef', 'altitude'],
                    ],
                    [
                        'title'           => 'Trek Jugurtha Tableland',
                        'description'     => 'Ascension du plateau de Jugurtha, forteresse naturelle à la frontière tuniso-algérienne. 2 jours de trekking sur ce site historique et géologique unique.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-10-16',
                        'end_date'        => '2026-10-17',
                        'capacity'        => 15,
                        'price'           => 55.00,
                        'remaining_spots' => 6,
                        'difficulty'      => 'difficult',
                        'hiking_duration' => 14.0,
                        'elevation_gain'  => 600,
                        'latitude'        => 35.5667,
                        'longitude'       => 8.3833,
                        'address'         => 'Table de Jugurtha, Kef, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['jugurtha', 'plateau', 'histoire', 'frontiere'],
                    ],
                    [
                        'title'           => 'Camping Forêt du Kef',
                        'description'     => 'Weekend camping dans les forêts de pins et de chênes du Kef. Randonnées, cueillette de champignons en saison et soirée traditionnelle.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-09-12',
                        'end_date'        => '2026-09-13',
                        'capacity'        => 25,
                        'price'           => 35.00,
                        'remaining_spots' => 9,
                        'difficulty'      => 'moderate',
                        'latitude'        => 36.1742,
                        'longitude'       => 8.7147,
                        'address'         => 'Forêt du Kef, Le Kef, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'foret', 'kef', 'mushrooms'],
                    ],
                ],
            ],

            // ── Ecotourisme Jendouba ──────────────────────────────────────────
            [
                'email' => 'ecotourisme.jendouba@gmail.com',
                'events' => [
                    [
                        'title'           => 'Éco-Randonnée Aïn Draham',
                        'description'     => 'Randonnée écologique dans les forêts de chênes-liège d\'Aïn Draham avec un guide naturaliste. Identification des plantes médicinales et observation des chênes-lièges.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-04-18',
                        'end_date'        => '2026-04-18',
                        'capacity'        => 25,
                        'price'           => 12.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'easy',
                        'hiking_duration' => 4.0,
                        'elevation_gain'  => 180,
                        'latitude'        => 36.7769,
                        'longitude'       => 8.6881,
                        'address'         => 'Aïn Draham, Jendouba, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['eco', 'foret', 'plantes', 'naturaliste', 'ain draham'],
                    ],
                    [
                        'title'           => 'Camping Éco Lac Beni M\'tir',
                        'description'     => 'Weekend camping écologique au bord du lac Beni M\'tir. Pêche, kayak, randonnée et ateliers sensibilisation à l\'environnement.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-07-31',
                        'end_date'        => '2026-08-01',
                        'capacity'        => 20,
                        'price'           => 40.00,
                        'remaining_spots' => 7,
                        'difficulty'      => 'moderate',
                        'latitude'        => 36.7167,
                        'longitude'       => 8.7833,
                        'address'         => 'Lac Beni M\'tir, Jendouba, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['eco', 'lac', 'kayak', 'environnement'],
                    ],
                ],
            ],

            // ── Outdoor Club Siliana ──────────────────────────────────────────
            [
                'email' => 'outdoor.club.siliana@gmail.com',
                'events' => [
                    [
                        'title'           => 'Trek Jebel Serj',
                        'description'     => 'Randonnée sur le Jebel Serj, montagne emblématique de la Dorsale tunisienne. Paysages sauvages, oiseaux de proie et vue sur la steppe et les collines.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-03-21',
                        'end_date'        => '2026-03-21',
                        'capacity'        => 22,
                        'price'           => 15.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'moderate',
                        'hiking_duration' => 6.0,
                        'elevation_gain'  => 480,
                        'latitude'        => 36.0833,
                        'longitude'       => 9.3667,
                        'address'         => 'Jebel Serj, Siliana, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['randonnee', 'montagne', 'siliana', 'dorsale'],
                    ],
                    [
                        'title'           => 'Camping Weekend Forêt Siliana',
                        'description'     => 'Weekend camping dans la forêt naturelle de Siliana. Programme détente : randonnées courtes, cueillette, feu de camp et nuit sous les étoiles.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-08-15',
                        'end_date'        => '2026-08-16',
                        'capacity'        => 28,
                        'price'           => 28.00,
                        'remaining_spots' => 11,
                        'difficulty'      => 'easy',
                        'latitude'        => 36.0833,
                        'longitude'       => 9.3667,
                        'address'         => 'Forêt de Siliana, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['camping', 'foret', 'siliana', 'detente'],
                    ],
                ],
            ],

            // ── Wilderness Tunis ──────────────────────────────────────────────
            [
                'email' => 'wilderness.tunis@gmail.com',
                'events' => [
                    [
                        'title'           => 'Bivouac Jebel Zaghouan',
                        'description'     => 'Bivouac au pied du Jebel Zaghouan (1295m), avec montée nocturne optionnelle jusqu\'au temple des eaux romain. Nuit froide garantie et lever de soleil inoubliable.',
                        'event_type'      => 'camping',
                        'start_date'      => '2026-01-30',
                        'end_date'        => '2026-01-31',
                        'capacity'        => 15,
                        'price'           => 35.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'difficult',
                        'latitude'        => 36.3833,
                        'longitude'       => 10.1500,
                        'address'         => 'Jebel Zaghouan, Zaghouan, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['bivouac', 'zaghouan', 'romain', 'nuit'],
                    ],
                    [
                        'title'           => 'Trek Nocturne Sidi Bou Saïd',
                        'description'     => 'Randonnée nocturne de Tunis à Sidi Bou Saïd en longeant les crêtes. Vues nocturnes sur le golfe de Tunis et la capitale. Petit-déjeuner à l\'arrivée inclus.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-04-10',
                        'end_date'        => '2026-04-11',
                        'capacity'        => 25,
                        'price'           => 25.00,
                        'remaining_spots' => 0,
                        'difficulty'      => 'moderate',
                        'hiking_duration' => 7.0,
                        'elevation_gain'  => 200,
                        'latitude'        => 36.8686,
                        'longitude'       => 10.3467,
                        'address'         => 'Sidi Bou Saïd, Tunis, Tunisie',
                        'status'          => 'finished',
                        'tags'            => ['nocturne', 'tunis', 'crete', 'panorama'],
                    ],
                    [
                        'title'           => 'Expédition Montagne Boukornine',
                        'description'     => 'Trek complet du parc national de Boukornine. Flore méditerranéenne rare, rapaces nicheurs et vue panoramique sur Tunis et le golfe. 2 jours avec nuit en tente.',
                        'event_type'      => 'hiking',
                        'start_date'      => '2026-09-26',
                        'end_date'        => '2026-09-27',
                        'capacity'        => 18,
                        'price'           => 50.00,
                        'remaining_spots' => 7,
                        'difficulty'      => 'difficult',
                        'hiking_duration' => 12.0,
                        'elevation_gain'  => 576,
                        'latitude'        => 36.7167,
                        'longitude'       => 10.2833,
                        'address'         => 'Parc National Boukornine, Ben Arous, Tunisie',
                        'status'          => 'scheduled',
                        'tags'            => ['boukornine', 'parc', 'rapaces', 'tunis'],
                    ],
                ],
            ],
        ];
    }
}
