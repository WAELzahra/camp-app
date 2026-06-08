<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CampingEventsSeeder
 *
 * Inserts 20 realistic camping events across Tunisia, distributed among existing
 * organizer users (role = 'organizer').  The seeder is idempotent — it skips
 * execution if the sentinel event already exists.
 *
 * Event mix covers:
 *  - Types  : camping · hiking · voyage
 *  - Status : finished · scheduled · full · canceled
 *  - Pricing: free (0 TND) and paid (10–180 TND)
 *  - Edge-cases: fully-booked, nearly-full, no-participants-yet, cancelled
 */
class CampingEventsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        // ── Idempotency guard ──────────────────────────────────────────────────
        if (DB::table('events')
            ->where('title', 'Bivouac Étoilé Douz — Nuit Saharienne')
            ->exists()
        ) {
            $this->command?->warn('⚠️  CampingEventsSeeder already run — skipping.');
            return;
        }

        // ── Load organizer user IDs ─────────────────────────────────────────
        $organizerRoleId = DB::table('roles')->where('name', 'organizer')->value('id');
        $organizers      = DB::table('users')
            ->where('role_id', $organizerRoleId)
            ->pluck('id', 'email')
            ->all();

        $rows    = [];
        $skipped = 0;

        foreach ($this->eventDefinitions() as $def) {
            $groupId = $organizers[$def['organizer_email']] ?? null;
            if (! $groupId) {
                $this->command?->warn("Organizer not found: {$def['organizer_email']} — skipping event.");
                $skipped++;
                continue;
            }

            $rows[] = [
                'group_id'               => $groupId,
                'title'                  => $def['title'],
                'description'            => $def['description'],
                'event_type'             => $def['event_type'],
                'start_date'             => $def['start_date'],
                'end_date'               => $def['end_date'],
                'capacity'               => $def['capacity'],
                'price'                  => $def['price'],
                'remaining_spots'        => $def['remaining_spots'],
                // Difficulty: only relevant for hiking
                'difficulty'             => $def['difficulty'] ?? null,
                // Hiking-specific
                'hiking_duration'        => $def['hiking_duration'] ?? null,
                'elevation_gain'         => $def['elevation_gain'] ?? null,
                // Camping-specific
                'camping_duration'       => $def['camping_duration'] ?? null,
                'camping_gear'           => $def['camping_gear'] ?? null,
                // Group travel (voyage type)
                'is_group_travel'        => $def['is_group_travel'] ?? false,
                'departure_city'         => $def['departure_city'] ?? null,
                'arrival_city'           => $def['arrival_city'] ?? null,
                'departure_time'         => $def['departure_time'] ?? null,
                'estimated_arrival_time' => $def['estimated_arrival_time'] ?? null,
                'bus_company'            => $def['bus_company'] ?? null,
                'bus_number'             => $def['bus_number'] ?? null,
                'city_stops'             => isset($def['city_stops'])
                                            ? json_encode($def['city_stops'], JSON_UNESCAPED_UNICODE)
                                            : null,
                // Location
                'latitude'               => $def['latitude'] ?? null,
                'longitude'              => $def['longitude'] ?? null,
                'address'                => $def['address'] ?? null,
                // Meta
                'tags'                   => json_encode($def['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                'is_active'              => in_array($def['status'], ['scheduled', 'ongoing', 'full']),
                'status'                 => $def['status'],
                'views_count'            => $def['views_count'],
                'created_at'             => $now,
                'updated_at'             => $now,
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('events')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($rows) . ' events inserted by CampingEventsSeeder.'
            . ($skipped ? " ({$skipped} skipped — organizer not found)" : ''));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Event definitions
    // Remaining-spots are set to reflect the planned reservations seeded by
    // CampingEventReservationsSeeder:
    //   - finished  → 0 (event is over)
    //   - full      → 0
    //   - scheduled → capacity − confirmed_places
    //   - canceled  → capacity (all spots freed)
    // ──────────────────────────────────────────────────────────────────────────
    private function eventDefinitions(): array
    {
        return [

            // ══════════════════════════════════════════════════════════════════
            // FINISHED EVENTS  (eligible for feedbacks / reviews)
            // ══════════════════════════════════════════════════════════════════

            // 1. Luxury desert bivouac — Douz (6 confirmed, 2 cancelled)
            [
                'organizer_email' => 'bivouac.sahara@gmail.com',
                'title'           => 'Bivouac Étoilé Douz — Nuit Saharienne',
                'description'     => 'Une nuit inoubliable au cœur des dunes de Douz. Tentes berbères '
                    . 'traditionnelles, dîner sous les étoiles, musique gnawa et lever de soleil sur l\'erg. '
                    . 'Expérience saharienne authentique pour un groupe restreint.',
                'event_type'      => 'camping',
                'start_date'      => '2025-11-21',
                'end_date'        => '2025-11-22',
                'capacity'        => 12,
                'price'           => 120.00,
                'remaining_spots' => 0,
                'camping_duration'=> 1,
                'camping_gear'    => 'Tente berbère fournie, sac de couchage, lampe frontale',
                'latitude'        => 33.4563,
                'longitude'       => 9.0247,
                'address'         => 'Erg Oriental, Douz, Kébili, Tunisie',
                'status'          => 'finished',
                'tags'            => ['bivouac', 'sahara', 'etoiles', 'dunes', 'douz', 'luxe'],
                'views_count'     => 892,
            ],

            // 2. Forest hiking — Aïn Draham (4 confirmed, 2 cancelled)
            [
                'organizer_email' => 'ecotourisme.jendouba@gmail.com',
                'title'           => 'Randonnée Forêt d\'Aïn Draham — Chênes-Liège',
                'description'     => 'Traversée des forêts de chênes-liège et de chênes-zéen autour '
                    . 'd\'Aïn Draham. Rencontre avec la flore endémique de la Kroumirie, passage par des '
                    . 'sources naturelles et panorama sur la vallée de l\'Oued Ghézala.',
                'event_type'      => 'hiking',
                'start_date'      => '2025-12-06',
                'end_date'        => '2025-12-06',
                'capacity'        => 30,
                'price'           => 20.00,
                'remaining_spots' => 0,
                'difficulty'      => 'easy',
                'hiking_duration' => 5.0,
                'elevation_gain'  => 280,
                'latitude'        => 36.7769,
                'longitude'       => 8.6881,
                'address'         => 'Aïn Draham, Jendouba, Tunisie',
                'status'          => 'finished',
                'tags'            => ['randonnee', 'foret', 'kroumirie', 'aindraham', 'nature'],
                'views_count'     => 445,
            ],

            // 3. Luxury oasis — Ksar Ghilane (8 confirmed + 2 refunded = FULL cap=10)
            [
                'organizer_email' => 'desert.explorers.tozeur@gmail.com',
                'title'           => 'Nuit Étoilée à Ksar Ghilane — Oasis & Dunes',
                'description'     => 'Séjour premium dans l\'oasis de Ksar Ghilane, à la frontière du '
                    . 'Grand Erg Oriental. Sources thermales naturelles, balade en dromadaire, dîner '
                    . 'gastronomique sous les palmiers et nuit en tente panoramique.',
                'event_type'      => 'camping',
                'start_date'      => '2025-12-19',
                'end_date'        => '2025-12-20',
                'capacity'        => 10,
                'price'           => 150.00,
                'remaining_spots' => 0,         // fully booked — sold-out luxury event
                'camping_duration'=> 1,
                'camping_gear'    => 'Tente panoramique fournie, draps, serviettes inclus',
                'latitude'        => 32.9875,
                'longitude'       => 9.6156,
                'address'         => 'Ksar Ghilane, Tataouine, Tunisie',
                'status'          => 'finished',
                'tags'            => ['camping', 'luxe', 'oasis', 'dromadaire', 'ksar-ghilane', 'complet'],
                'views_count'     => 1120,
            ],

            // 4. Coastal hiking — Cap Serrat (4 confirmed, 2 cancelled)
            [
                'organizer_email' => 'mer.montagne.bizerte@gmail.com',
                'title'           => 'Randonnée Littorale Cap Serrat',
                'description'     => 'Randonnée côtière de 14 km le long des falaises de Cap Serrat, '
                    . 'point le plus sauvage du nord tunisien. Plages immaculées, forêt de pins maritimes '
                    . 'et panorama exceptionnel sur la mer Méditerranée.',
                'event_type'      => 'hiking',
                'start_date'      => '2026-01-10',
                'end_date'        => '2026-01-10',
                'capacity'        => 25,
                'price'           => 15.00,
                'remaining_spots' => 0,
                'difficulty'      => 'moderate',
                'hiking_duration' => 6.0,
                'elevation_gain'  => 320,
                'latitude'        => 37.2333,
                'longitude'       => 9.2167,
                'address'         => 'Cap Serrat, Bizerte, Tunisie',
                'status'          => 'finished',
                'tags'            => ['randonnee', 'cotier', 'cap-serrat', 'bizerte', 'falaises'],
                'views_count'     => 388,
            ],

            // 5. Mountain hiking — Jebel Zaghouan (4 confirmed, 2 cancelled)
            [
                'organizer_email' => 'outdoor.club.siliana@gmail.com',
                'title'           => 'Trek Jebel Zaghouan — Temple des Eaux',
                'description'     => 'Ascension du Jebel Zaghouan (1295 m) jusqu\'au temple des Eaux '
                    . 'romain. Sentier bien tracé avec passages rocheux, vue panoramique sur la plaine '
                    . 'de Tunis et les collines du Sahel. Visite du temple antique au sommet.',
                'event_type'      => 'hiking',
                'start_date'      => '2026-02-07',
                'end_date'        => '2026-02-07',
                'capacity'        => 20,
                'price'           => 18.00,
                'remaining_spots' => 0,
                'difficulty'      => 'moderate',
                'hiking_duration' => 5.5,
                'elevation_gain'  => 850,
                'latitude'        => 36.3833,
                'longitude'       => 10.1667,
                'address'         => 'Jebel Zaghouan, Zaghouan, Tunisie',
                'status'          => 'finished',
                'tags'            => ['randonnee', 'montagne', 'zaghouan', 'temple', 'romain', 'sommet'],
                'views_count'     => 312,
            ],

            // 6. Gorge hiking — Oued Zitoun (3 confirmed, 1 cancelled)
            [
                'organizer_email' => 'aventuriers.kef@gmail.com',
                'title'           => 'Trek Oued Zitoun & Gorges du Kef',
                'description'     => 'Randonnée dans les gorges de l\'Oued Zitoun au cœur du Tell '
                    . 'occidental. Remontée du lit de la rivière, passages à gué et découverte des grottes '
                    . 'troglodytiques des pentes du Jebel Dyr.',
                'event_type'      => 'hiking',
                'start_date'      => '2026-02-28',
                'end_date'        => '2026-02-28',
                'capacity'        => 18,
                'price'           => 22.00,
                'remaining_spots' => 0,
                'difficulty'      => 'moderate',
                'hiking_duration' => 6.5,
                'elevation_gain'  => 410,
                'latitude'        => 36.1744,
                'longitude'       => 8.7083,
                'address'         => 'Le Kef, Tunisie',
                'status'          => 'finished',
                'tags'            => ['randonnee', 'gorges', 'kef', 'oued', 'grottes'],
                'views_count'     => 267,
            ],

            // 7. Family camping — Hammamet (FULLY BOOKED: 10 × nbr_place=4 = 40 places)
            [
                'organizer_email' => 'camp.family.nabeul@gmail.com',
                'title'           => 'Grand Camping Familial Hammamet — Spring Edition',
                'description'     => 'Weekend camping en famille sur la plage de Hammamet Nord. '
                    . 'Activités aquatiques, volley de plage, barbecue géant, quiz nature et veillée '
                    . 'autour du feu. Adapté aux enfants dès 3 ans.',
                'event_type'      => 'camping',
                'start_date'      => '2026-03-21',
                'end_date'        => '2026-03-23',
                'capacity'        => 40,
                'price'           => 35.00,
                'remaining_spots' => 0,         // fully booked
                'camping_duration'=> 2,
                'camping_gear'    => 'Emplacement fourni, jeux pour enfants, barbecue commun',
                'latitude'        => 36.3992,
                'longitude'       => 10.5528,
                'address'         => 'Hammamet Nord, Nabeul, Tunisie',
                'status'          => 'finished',
                'tags'            => ['camping', 'famille', 'plage', 'hammamet', 'enfants', 'complet'],
                'views_count'     => 623,
            ],

            // 8. Desert oasis — Tamerza (4 confirmed, 1 cancelled, 1 refunded)
            [
                'organizer_email' => 'desert.explorers.tozeur@gmail.com',
                'title'           => 'Camping Oasis de Tamerza — Canyon & Cascade',
                'description'     => 'Weekend dans l\'oasis montagnarde de Tamerza, village troglodytique '
                    . 'et cascade aux eaux turquoise. Randonnée dans le canyon, nuit en bivouac et '
                    . 'découverte du ksour abandonné.',
                'event_type'      => 'camping',
                'start_date'      => '2026-03-14',
                'end_date'        => '2026-03-15',
                'capacity'        => 20,
                'price'           => 85.00,
                'remaining_spots' => 0,
                'difficulty'      => 'easy',
                'camping_duration'=> 1,
                'camping_gear'    => 'Tente, sac de couchage, lampe frontale recommandés',
                'latitude'        => 34.3467,
                'longitude'       => 7.9456,
                'address'         => 'Tamerza, Tozeur, Tunisie',
                'status'          => 'finished',
                'tags'            => ['camping', 'oasis', 'tamerza', 'canyon', 'cascade', 'desert'],
                'views_count'     => 534,
            ],

            // 9. Cultural coastal hike — Sidi Bou Said (4 confirmed, 2 cancelled)
            [
                'organizer_email' => 'jeunesse.nature.monastir@gmail.com',
                'title'           => 'Randonnée Côtière Sidi Bou Said — Carthage',
                'description'     => 'Randonnée culturelle et paysagère reliant Sidi Bou Said aux ruines '
                    . 'de Carthage en longeant la côte. Vue sur le golfe de Tunis, visite des sites '
                    . 'archéologiques et dégustation de thé à la menthe.',
                'event_type'      => 'hiking',
                'start_date'      => '2026-04-04',
                'end_date'        => '2026-04-04',
                'capacity'        => 35,
                'price'           => 10.00,
                'remaining_spots' => 0,
                'difficulty'      => 'easy',
                'hiking_duration' => 4.0,
                'elevation_gain'  => 80,
                'latitude'        => 36.8694,
                'longitude'       => 10.3411,
                'address'         => 'Sidi Bou Said, Tunis, Tunisie',
                'status'          => 'finished',
                'tags'            => ['randonnee', 'cotier', 'culture', 'sidi-bou-said', 'carthage'],
                'views_count'     => 756,
            ],

            // ══════════════════════════════════════════════════════════════════
            // SCHEDULED EVENTS  (upcoming, accepting reservations)
            // remaining_spots = capacity − confirmed_places already reserved
            // ══════════════════════════════════════════════════════════════════

            // 10. Beach camping — Zouaraa (3 confirmed → remaining=37)
            [
                'organizer_email' => 'camp.family.nabeul@gmail.com',
                'title'           => 'Camping Plage de Zouaraa — Été 2026',
                'description'     => 'Camping de plage à Zouaraa, au nord de Bizerte, l\'une des plus '
                    . 'belles plages vierges de Tunisie. Eaux cristallines, sable blanc et ciel étoilé. '
                    . 'Activités snorkeling, kayak et volley-ball de plage.',
                'event_type'      => 'camping',
                'start_date'      => '2026-07-11',
                'end_date'        => '2026-07-13',
                'capacity'        => 40,
                'price'           => 30.00,
                'remaining_spots' => 37,
                'camping_duration'=> 2,
                'camping_gear'    => 'Emplacement, tables et chaises fournis',
                'latitude'        => 37.2942,
                'longitude'       => 9.6667,
                'address'         => 'Plage de Zouaraa, Bizerte, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['camping', 'plage', 'zouaraa', 'bizerte', 'ete'],
                'views_count'     => 287,
            ],

            // 11. Summit hike — Jebel Chambi (17 confirmed → remaining=3, NEARLY FULL)
            [
                'organizer_email' => 'mountain.climbers.beja@gmail.com',
                'title'           => 'Ascension Jebel Chambi — Toit de Tunisie',
                'description'     => 'Ascension du Jebel Chambi (1544 m), point culminant de la Tunisie. '
                    . 'Sentier technique avec passages rocheux, panorama à 360° sur les plaines de '
                    . 'Kasserine et les chaînes des Aurès.',
                'event_type'      => 'hiking',
                'start_date'      => '2026-07-25',
                'end_date'        => '2026-07-25',
                'capacity'        => 20,
                'price'           => 25.00,
                'remaining_spots' => 3,         // edge case: almost full — only 3 spots left
                'difficulty'      => 'difficult',
                'hiking_duration' => 8.0,
                'elevation_gain'  => 1200,
                'latitude'        => 35.2167,
                'longitude'       => 8.6667,
                'address'         => 'Jebel Chambi, Kasserine, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['randonnee', 'montagne', 'chambi', 'sommet', 'kasserine', 'difficile'],
                'views_count'     => 512,
            ],

            // 12. Canyon trek — Gorges de Selja (2 confirmed → remaining=23)
            [
                'organizer_email' => 'trek.sud.tunisie@gmail.com',
                'title'           => 'Trek Gorges de Selja — Canyon Rouge',
                'description'     => 'Trek dans les spectaculaires gorges de Selja, surnommées le '
                    . '«Grand Canyon de Tunisie». Parois de grès rouge, palmiers nains et rivière '
                    . 'saisonnière. L\'un des treks les plus photogéniques du pays.',
                'event_type'      => 'hiking',
                'start_date'      => '2026-08-08',
                'end_date'        => '2026-08-09',
                'capacity'        => 25,
                'price'           => 35.00,
                'remaining_spots' => 23,
                'difficulty'      => 'moderate',
                'hiking_duration' => 10.0,
                'elevation_gain'  => 550,
                'latitude'        => 34.5617,
                'longitude'       => 8.7883,
                'address'         => 'Gorges de Selja, Gafsa, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['trek', 'gorges', 'selja', 'canyon', 'gafsa', 'nature'],
                'views_count'     => 334,
            ],

            // 13. FREE camping — Beni Mtir (3 confirmed → remaining=32)
            [
                'organizer_email' => 'wilderness.tunis@gmail.com',
                'title'           => 'Camping Gratuit Beni Mtir — Lac & Forêt',
                'description'     => 'Weekend camping gratuit au bord du lac de retenue de Beni Mtir, '
                    . 'entouré de forêts de pins et de chênes. Baignade, pêche, randonnée libre et feu '
                    . 'de camp. Esprit sauvage et convivial, venez comme vous êtes !',
                'event_type'      => 'camping',
                'start_date'      => '2026-08-15',
                'end_date'        => '2026-08-16',
                'capacity'        => 35,
                'price'           => 0.00,      // FREE event
                'remaining_spots' => 32,
                'camping_duration'=> 1,
                'camping_gear'    => 'Apportez votre propre matériel — accès eau potable sur place',
                'latitude'        => 36.9386,
                'longitude'       => 8.5881,
                'address'         => 'Lac de Beni Mtir, Jendouba, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['camping', 'gratuit', 'lac', 'beni-mtir', 'foret', 'sauvage'],
                'views_count'     => 198,
            ],

            // 14. Diving camp — Tabarka (2 confirmed → remaining=28)
            [
                'organizer_email' => 'coastal.explorers.mahdia@gmail.com',
                'title'           => 'Camping & Plongée Tabarka — Récifs Coralliens',
                'description'     => 'Weekend immersion à Tabarka : camping sous les pins maritimes, '
                    . 'plongée sur les récifs coralliens et les groupes de mérous, randonnée jusqu\'aux '
                    . 'ruines du Château Génois.',
                'event_type'      => 'camping',
                'start_date'      => '2026-09-05',
                'end_date'        => '2026-09-07',
                'capacity'        => 30,
                'price'           => 45.00,
                'remaining_spots' => 28,
                'camping_duration'=> 2,
                'camping_gear'    => 'Matériel de plongée fourni (bouteilles, combinaisons)',
                'latitude'        => 36.9544,
                'longitude'       => 8.5562,
                'address'         => 'Tabarka, Jendouba, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['camping', 'plongee', 'tabarka', 'coraux', 'mer'],
                'views_count'     => 421,
            ],

            // 15. Group voyage — Djerba (3 confirmed + pending → remaining=47)
            [
                'organizer_email' => 'nature.lovers.sousse@gmail.com',
                'title'           => 'Voyage Découverte Djerba — Île des Rêves',
                'description'     => 'Voyage organisé à Djerba : ferry depuis Sfax, camp de base à '
                    . 'Midoun, randonnée à vélo à travers les oliveraies et les villages berbères, visite '
                    . 'de la Ghriba et coucher de soleil à la plage de Sidi Jmour.',
                'event_type'      => 'voyage',
                'start_date'      => '2026-09-18',
                'end_date'        => '2026-09-21',
                'capacity'        => 50,
                'price'           => 55.00,
                'remaining_spots' => 47,
                'is_group_travel' => true,
                'departure_city'  => 'Sousse',
                'arrival_city'    => 'Djerba',
                'departure_time'  => '06:00',
                'estimated_arrival_time' => '12:00',
                'bus_company'     => 'Transtu Voyages',
                'bus_number'      => 'TV-2026-DJ01',
                'city_stops'      => ['Sfax', 'Gabès'],
                'latitude'        => 33.8167,
                'longitude'       => 10.8500,
                'address'         => 'Djerba, Médenine, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['voyage', 'djerba', 'ile', 'culture', 'velo', 'ghriba'],
                'views_count'     => 645,
            ],

            // 16. Night hike — Bou Kornine (3 confirmed → remaining=22)
            [
                'organizer_email' => 'club.aventure.tunis@gmail.com',
                'title'           => 'Randonnée Nocturne Jebel Bou Kornine',
                'description'     => 'Randonnée nocturne au Jebel Bou Kornine (576 m), aux portes de '
                    . 'Tunis. Départ au coucher du soleil, lampes frontales obligatoires, observation de '
                    . 'la bioluminescence côtière et retour à l\'aube avec vue sur Tunis illuminée.',
                'event_type'      => 'hiking',
                'start_date'      => '2026-07-18',
                'end_date'        => '2026-07-19',
                'capacity'        => 25,
                'price'           => 12.00,
                'remaining_spots' => 22,
                'difficulty'      => 'easy',
                'hiking_duration' => 5.0,
                'elevation_gain'  => 450,
                'latitude'        => 36.7167,
                'longitude'       => 10.5333,
                'address'         => 'Jebel Bou Kornine, Ben Arous, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['randonnee', 'nocturne', 'bou-kornine', 'tunis', 'etoiles'],
                'views_count'     => 389,
            ],

            // ══════════════════════════════════════════════════════════════════
            // FULL EVENT  (status = 'full', remaining_spots = 0)
            // ══════════════════════════════════════════════════════════════════

            // 17. Expert traverse — SOLD OUT (15 confirmed, all spots taken)
            [
                'organizer_email' => 'mountain.climbers.beja@gmail.com',
                'title'           => 'Traversée de la Dorsale Tunisienne — 5 Jours',
                'description'     => 'Expédition de 5 jours traversant la dorsale tunisienne de Jebel '
                    . 'Chambi à Jebel Bargou. Bivouacs en altitude, passages techniques et panoramas '
                    . 'époustouflants. Niveau confirmé requis. *** COMPLET — liste d\'attente ouverte ***',
                'event_type'      => 'hiking',
                'start_date'      => '2026-10-10',
                'end_date'        => '2026-10-14',
                'capacity'        => 15,
                'price'           => 180.00,
                'remaining_spots' => 0,         // FULLY BOOKED
                'difficulty'      => 'expert',
                'hiking_duration' => 40.0,
                'elevation_gain'  => 4500,
                'latitude'        => 35.8833,
                'longitude'       => 8.9667,
                'address'         => 'Dorsale Tunisienne, Kasserine — Siliana, Tunisie',
                'status'          => 'full',
                'tags'            => ['traverse', 'dorsale', 'expert', 'bivouac', 'complet', '5-jours'],
                'views_count'     => 1340,
            ],

            // ══════════════════════════════════════════════════════════════════
            // SCHEDULED — EMPTY (no participants yet, recently published)
            // ══════════════════════════════════════════════════════════════════

            // 18. Desert bivouac — Jebel Orbata (0 reservations yet)
            [
                'organizer_email' => 'aventure.sfax@gmail.com',
                'title'           => 'Bivouac Jebel Orbata — Coucher de Soleil sur les Chotts',
                'description'     => 'Bivouac nocturne sur les hauteurs du Jebel Orbata avec vue '
                    . 'imprenable sur le Chott el-Jérid. Marche d\'approche de 3h, installation du '
                    . 'bivouac au coucher du soleil, observation des étoiles.',
                'event_type'      => 'camping',
                'start_date'      => '2026-11-07',
                'end_date'        => '2026-11-08',
                'capacity'        => 15,
                'price'           => 30.00,
                'remaining_spots' => 15,        // no one registered yet
                'difficulty'      => 'moderate',
                'camping_duration'=> 1,
                'camping_gear'    => 'Bivouac léger, duvet −5 °C recommandé',
                'latitude'        => 34.5833,
                'longitude'       => 8.7833,
                'address'         => 'Jebel Orbata, Gafsa, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['bivouac', 'orbata', 'chotts', 'etoiles', 'sfax'],
                'views_count'     => 87,
            ],

            // 19. FREE camping — Nebhana (0 reservations yet)
            [
                'organizer_email' => 'randonneurs.kairouan@gmail.com',
                'title'           => 'Weekend Lac de Nebhana — Camping Libre',
                'description'     => 'Weekend au bord du barrage de Nebhana, entre oliveraies et steppe. '
                    . 'Camping libre, pêche, randonnée sur les berges et exploration des grottes '
                    . 'troglodytiques voisines. Accessible à tous niveaux.',
                'event_type'      => 'camping',
                'start_date'      => '2026-10-23',
                'end_date'        => '2026-10-25',
                'capacity'        => 40,
                'price'           => 0.00,      // FREE
                'remaining_spots' => 40,        // no one registered yet
                'camping_duration'=> 2,
                'camping_gear'    => 'Matériel personnel, eau en bouteille conseillée',
                'latitude'        => 35.7083,
                'longitude'       => 9.4500,
                'address'         => 'Barrage de Nebhana, Kairouan, Tunisie',
                'status'          => 'scheduled',
                'tags'            => ['camping', 'gratuit', 'nebhana', 'kairouan', 'lac'],
                'views_count'     => 52,
            ],

            // ══════════════════════════════════════════════════════════════════
            // CANCELED EVENT  (all reservations were annulled by organizer)
            // ══════════════════════════════════════════════════════════════════

            // 20. Canceled — weather forced postponement
            [
                'organizer_email' => 'wilderness.tunis@gmail.com',
                'title'           => 'Camping Sauvage Zouaraa — Édition Annulée',
                'description'     => '⚠️ ANNULÉ — Cette édition a été annulée en raison de conditions '
                    . 'météorologiques défavorables (alerte rouge vents violents). Remboursements intégraux '
                    . 'effectués. Une nouvelle date sera annoncée prochainement sur notre page.',
                'event_type'      => 'camping',
                'start_date'      => '2026-05-10',
                'end_date'        => '2026-05-11',
                'capacity'        => 30,
                'price'           => 0.00,
                'remaining_spots' => 30,        // spots freed after cancellation
                'camping_duration'=> 1,
                'camping_gear'    => null,
                'latitude'        => 37.2942,
                'longitude'       => 9.6667,
                'address'         => 'Plage de Zouaraa, Bizerte, Tunisie',
                'status'          => 'canceled',
                'tags'            => ['camping', 'annule', 'zouaraa', 'reporte'],
                'views_count'     => 143,
            ],
        ];
    }
}
