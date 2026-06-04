<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnnoncesAndSocialSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('comment_likes')->truncate();
        DB::table('annonce_likes')->truncate();
        DB::table('comments')->truncate();
        DB::table('annonces')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Load reference data ────────────────────────────────────────────────
        $groupeRoleId  = DB::table('roles')->where('name', 'groupe')->value('id');
        $campeurRoleId = DB::table('roles')->where('name', 'campeur')->value('id');

        /** email → user_id */
        $groupeUsers = DB::table('users')
            ->where('role_id', $groupeRoleId)
            ->pluck('id', 'email')
            ->all();

        /** user_id → profile_groupes.id */
        $pgByUserId = DB::table('profile_groupes as pg')
            ->join('profiles as p', 'p.id', '=', 'pg.profile_id')
            ->pluck('pg.id', 'p.user_id')
            ->all();

        /** array of campeur user_ids */
        $campeurIds = DB::table('users')
            ->where('role_id', $campeurRoleId)
            ->pluck('id')
            ->all();

        /** profile_groupes.id → [campeur user_ids who follow] */
        $followersByPg = [];
        foreach (DB::table('followers_groupes')->get() as $f) {
            $followersByPg[$f->groupe_id][] = $f->user_id;
        }

        // ── Build and insert annonces ──────────────────────────────────────────
        $annonceDefs = $this->annonceDefs();
        $annonceRows = [];
        $idx = 0;

        foreach ($annonceDefs as $def) {
            $uid = $groupeUsers[$def['email']] ?? null;
            if (! $uid) continue;

            foreach ($def['annonces'] as $a) {
                $annonceRows[] = [
                    'user_id'        => $uid,
                    'title'          => $a['title'],
                    'description'    => $a['description'],
                    'start_date'     => $a['start_date'],
                    'end_date'       => $a['end_date'],
                    'auto_archive'   => 1,
                    'is_archived'    => $a['status'] === 'archived' ? 1 : 0,
                    'type'           => $a['type'],
                    'activities'     => json_encode($a['activities'], JSON_UNESCAPED_UNICODE),
                    'latitude'       => $a['latitude'],
                    'longitude'      => $a['longitude'],
                    'address'        => $a['address'],
                    'views_count'    => 50 + (($uid * 13 + $idx * 37) % 700),
                    'likes_count'    => 0,
                    'comments_count' => 0,
                    'status'         => $a['status'],
                    'created_at'     => $a['created_at'],
                    'updated_at'     => $a['created_at'],
                ];
                $idx++;
            }
        }

        foreach (array_chunk($annonceRows, 50) as $chunk) {
            DB::table('annonces')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($annonceRows) . ' annonces inserted.');

        // ── Query back inserted annonces ───────────────────────────────────────
        $allAnnonces = DB::table('annonces')->select('id', 'user_id', 'status')->get();

        // ── Build top-level comments and likes ─────────────────────────────────
        $topTexts   = $this->topLevelComments();
        $allTopRows = [];
        $likeRows   = [];
        $seenLikes  = [];

        foreach ($allAnnonces as $annonce) {
            if ($annonce->status !== 'approved') continue;

            $pgId      = $pgByUserId[$annonce->user_id] ?? null;
            $followers = $pgId ? ($followersByPg[$pgId] ?? []) : [];

            if (empty($followers)) {
                $off       = $annonce->id % max(1, count($campeurIds) - 6);
                $followers = array_slice($campeurIds, $off, 6);
            }

            // pick commenters deterministically
            $commentCount = 3 + ($annonce->id % 4);
            $totalPool    = array_values(array_unique(array_merge(
                $followers,
                array_diff($campeurIds, $followers)
            )));
            $off       = $annonce->id % max(1, count($totalPool));
            $commenters = [];
            for ($i = 0; $i < $commentCount && $i < count($totalPool); $i++) {
                $commenters[] = $totalPool[($off + $i) % count($totalPool)];
            }

            foreach ($commenters as $i => $uid) {
                $daysAgo = 5 + (($annonce->id * 3 + $i * 7) % 60);
                $allTopRows[] = [
                    'user_id'    => $uid,
                    'annonce_id' => $annonce->id,
                    'parent_id'  => null,
                    'content'    => $topTexts[($annonce->id + $i) % count($topTexts)],
                    'likes_count'=> ($annonce->id + $i) % 9,
                    'is_edited'  => 0,
                    'is_pinned'  => 0,
                    'is_hidden'  => 0,
                    'created_at' => now()->subDays($daysAgo)->toDateTimeString(),
                    'updated_at' => now()->subDays(max(0, $daysAgo - 2))->toDateTimeString(),
                    'deleted_at' => null,
                ];
            }

            // build likes
            $likeCount  = 5 + ($annonce->id % 21);
            $likePool   = array_values(array_unique(array_merge(
                $followers,
                array_diff($campeurIds, $followers)
            )));
            $likeOff = ($annonce->id * 5) % max(1, count($likePool));
            for ($i = 0; $i < $likeCount && $i < count($likePool); $i++) {
                $uid = $likePool[($likeOff + $i) % count($likePool)];
                $key = $uid . '_' . $annonce->id;
                if (isset($seenLikes[$key])) continue;
                $seenLikes[$key] = true;
                $likeRows[] = [
                    'user_id'    => $uid,
                    'annonce_id' => $annonce->id,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];
            }
        }

        foreach (array_chunk($allTopRows, 50) as $chunk) {
            DB::table('comments')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($allTopRows) . ' top-level comments inserted.');

        // ── Build replies using queried-back parent IDs ────────────────────────
        $replyTexts = $this->replyComments();
        $replyRows  = [];

        $topsByAnnonce = [];
        foreach (DB::table('comments')->whereNull('parent_id')->select('id', 'annonce_id', 'user_id')->get() as $c) {
            $topsByAnnonce[$c->annonce_id][] = $c;
        }

        foreach ($topsByAnnonce as $annonceId => $cList) {
            if (count($cList) < 2) continue;

            $replyCount = 1 + ($annonceId % 2);
            $usedUids   = array_map(fn($c) => $c->user_id, $cList);
            $available  = array_values(array_diff($campeurIds, $usedUids));
            if (empty($available)) continue;

            for ($r = 0; $r < min($replyCount, count($available)); $r++) {
                $parent  = $cList[$r % count($cList)];
                $daysAgo = 1 + (($annonceId + $r) % 25);
                $replyRows[] = [
                    'user_id'    => $available[($annonceId + $r) % count($available)],
                    'annonce_id' => $annonceId,
                    'parent_id'  => $parent->id,
                    'content'    => $replyTexts[($annonceId + $r) % count($replyTexts)],
                    'likes_count'=> ($annonceId + $r) % 5,
                    'is_edited'  => 0,
                    'is_pinned'  => 0,
                    'is_hidden'  => 0,
                    'created_at' => now()->subDays($daysAgo)->toDateTimeString(),
                    'updated_at' => now()->subDays(max(0, $daysAgo - 1))->toDateTimeString(),
                    'deleted_at' => null,
                ];
            }
        }

        foreach (array_chunk($replyRows, 50) as $chunk) {
            DB::table('comments')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($replyRows) . ' replies inserted.');

        foreach (array_chunk($likeRows, 50) as $chunk) {
            DB::table('annonce_likes')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($likeRows) . ' annonce likes inserted.');

        // ── Update denormalised counts ──────────────────────────────────────────
        foreach (DB::table('comments')->select('annonce_id', DB::raw('COUNT(*) as cnt'))->groupBy('annonce_id')->get() as $row) {
            DB::table('annonces')->where('id', $row->annonce_id)->update(['comments_count' => $row->cnt]);
        }
        foreach (DB::table('annonce_likes')->select('annonce_id', DB::raw('COUNT(*) as cnt'))->groupBy('annonce_id')->get() as $row) {
            DB::table('annonces')->where('id', $row->annonce_id)->update(['likes_count' => $row->cnt]);
        }

        $this->command?->info('✅ AnnoncesAndSocialSeeder complete.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Annonce definitions — 2 annonces per groupe (36 total)
    // ──────────────────────────────────────────────────────────────────────────
    private function annonceDefs(): array
    {
        return [
            [
                'email' => 'club.aventure.tunis@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Randonnée Jebel Zaghouan — Printemps 2025',
                        'description'=> 'Ascension autour du temple des eaux de Zaghouan. Paysages époustouflants, vue sur la plaine et les ruines romaines. Départ tôt le matin pour éviter la chaleur.',
                        'type'       => 'Randonnée',
                        'activities' => ['randonnée', 'histoire', 'photographie'],
                        'start_date' => '2025-11-22',
                        'end_date'   => '2025-11-22',
                        'latitude'   => '36.3972',
                        'longitude'  => '10.1086',
                        'address'    => 'Jebel Zaghouan, Zaghouan, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-11-10 09:00:00',
                    ],
                    [
                        'title'      => 'Bivouac Jebel Ressas — Nuit sous les étoiles',
                        'description'=> 'Bivouac au cœur du Jebel Ressas, à 30 km de Tunis. Randonnée de 3h, installation du camp en fin d\'après-midi et nuit étoilée dans un cadre sauvage préservé.',
                        'type'       => 'Bivouac montagne',
                        'activities' => ['bivouac', 'randonnée', 'astronomie'],
                        'start_date' => '2026-06-13',
                        'end_date'   => '2026-06-14',
                        'latitude'   => '36.6114',
                        'longitude'  => '10.4189',
                        'address'    => 'Jebel Ressas, Ben Arous, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-04-20 10:30:00',
                    ],
                ],
            ],
            [
                'email' => 'randonneurs.nord@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Sentiers d\'Aïn Draham — Forêt de chênes-lièges',
                        'description'=> 'Randonnée dans la magnifique forêt de chênes-lièges d\'Aïn Draham. Paysages verdoyants, cascades et villages berbères. Une sortie dépaysante à 800m d\'altitude.',
                        'type'       => 'Randonnée',
                        'activities' => ['randonnée', 'nature', 'forêt'],
                        'start_date' => '2025-10-18',
                        'end_date'   => '2025-10-19',
                        'latitude'   => '36.7794',
                        'longitude'  => '8.6875',
                        'address'    => 'Aïn Draham, Jendouba, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-10-01 08:00:00',
                    ],
                    [
                        'title'      => 'Découverte du Parc National de l\'Ichkeul',
                        'description'=> 'Visite du parc national de l\'Ichkeul, réserve de biosphère UNESCO. Observation des oiseaux migrateurs, randonnée autour du lac et ascension du Jebel Ichkeul pour une vue panoramique unique.',
                        'type'       => 'Sortie nature',
                        'activities' => ['ornithologie', 'randonnée', 'photographie nature'],
                        'start_date' => '2026-07-11',
                        'end_date'   => '2026-07-11',
                        'latitude'   => '37.1677',
                        'longitude'  => '9.6648',
                        'address'    => 'Parc National de l\'Ichkeul, Bizerte, Tunisie',
                        'status'     => 'pending',
                        'created_at' => '2026-05-01 11:00:00',
                    ],
                ],
            ],
            [
                'email' => 'desert.explorers.tozeur@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Bivouac Grand Erg Oriental — 3 jours',
                        'description'=> 'Trois jours d\'immersion dans le Grand Erg Oriental. Traversée de dunes à dos de dromadaire, bivouac sous un ciel étoilé exceptionnel et rencontre avec les habitants des oasis.',
                        'type'       => 'Bivouac désertique',
                        'activities' => ['dromadaire', 'bivouac', 'astronomie', 'désert'],
                        'start_date' => '2025-12-19',
                        'end_date'   => '2025-12-21',
                        'latitude'   => '33.9197',
                        'longitude'  => '8.1256',
                        'address'    => 'Grand Erg Oriental, Tozeur, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-11-28 09:00:00',
                    ],
                    [
                        'title'      => 'Caravane Ksar Ghilane — Oasis du bout du monde',
                        'description'=> 'Deux jours de trek jusqu\'à l\'oasis de Ksar Ghilane, la plus reculée de Tunisie. Source chaude naturelle, dunes à perte de vue et nuit au cœur du Sahara.',
                        'type'       => 'Voyage aventure',
                        'activities' => ['trek', 'oasis', 'dromadaire', 'source chaude'],
                        'start_date' => '2026-04-03',
                        'end_date'   => '2026-04-04',
                        'latitude'   => '32.9726',
                        'longitude'  => '9.6308',
                        'address'    => 'Ksar Ghilane, Tataouine, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-03-01 10:00:00',
                    ],
                ],
            ],
            [
                'email' => 'mer.montagne.bizerte@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Cap Blanc — Le point le plus nord de l\'Afrique',
                        'description'=> 'Randonnée côtière jusqu\'au Cap Blanc, point le plus septentrional du continent africain. Falaises spectaculaires, eaux turquoise et phare historique.',
                        'type'       => 'Sortie nature',
                        'activities' => ['randonnée côtière', 'photographie', 'histoire'],
                        'start_date' => '2025-09-27',
                        'end_date'   => '2025-09-27',
                        'latitude'   => '37.3442',
                        'longitude'  => '9.8631',
                        'address'    => 'Cap Blanc, Bizerte, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-09-10 09:00:00',
                    ],
                    [
                        'title'      => 'Trek Jebel Ichkeul — Automne 2026',
                        'description'=> 'Ascension du Jebel Ichkeul avec vue sur le lac et la mer. Rencontre avec la faune sauvage du parc national. Randonnée de 8 km, niveau intermédiaire recommandé.',
                        'type'       => 'Randonnée',
                        'activities' => ['randonnée', 'faune', 'parc national'],
                        'start_date' => '2026-10-17',
                        'end_date'   => '2026-10-17',
                        'latitude'   => '37.1677',
                        'longitude'  => '9.6648',
                        'address'    => 'Jebel Ichkeul, Mateur, Tunisie',
                        'status'     => 'pending',
                        'created_at' => '2026-05-05 14:00:00',
                    ],
                ],
            ],
            [
                'email' => 'camp.family.nabeul@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Week-end famille Hammamet — Plage et nature',
                        'description'=> 'Week-end familial à Hammamet. Camping sur la plage, activités pour enfants, baignade, barbecue et soirée conviviale sous les étoiles. Idéal pour les familles avec enfants de tous âges.',
                        'type'       => 'Camping familial',
                        'activities' => ['camping', 'baignade', 'jeux enfants', 'barbecue'],
                        'start_date' => '2025-08-01',
                        'end_date'   => '2025-08-03',
                        'latitude'   => '36.3833',
                        'longitude'  => '10.6167',
                        'address'    => 'Hammamet Beach, Nabeul, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-07-10 10:00:00',
                    ],
                    [
                        'title'      => 'Korbous — Sources thermales et nature en famille',
                        'description'=> 'Journée découverte à Korbous, station thermale perchée sur les falaises du golfe de Tunis. Randonnée légère, bain en mer et pique-nique en famille dans un site naturel exceptionnel.',
                        'type'       => 'Sortie nature',
                        'activities' => ['sources thermales', 'randonnée légère', 'pique-nique', 'baignade'],
                        'start_date' => '2026-05-30',
                        'end_date'   => '2026-05-30',
                        'latitude'   => '36.7964',
                        'longitude'  => '10.5561',
                        'address'    => 'Korbous, Cap Bon, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-05-01 12:00:00',
                    ],
                ],
            ],
            [
                'email' => 'aventure.sfax@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Trek Jebel Biada — Entre désert et oliveraies',
                        'description'=> 'Randonnée autour du Jebel Biada, entre paysages désertiques et immenses oliveraies sfaxiennes. Découverte de la faune endémique et des traditions agropastorales.',
                        'type'       => 'Randonnée',
                        'activities' => ['randonnée', 'faune endémique', 'oliveraies'],
                        'start_date' => '2025-10-25',
                        'end_date'   => '2025-10-26',
                        'latitude'   => '34.5156',
                        'longitude'  => '9.1734',
                        'address'    => 'Jebel Biada, Sfax, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-10-08 09:30:00',
                    ],
                    [
                        'title'      => 'Bivouac Iles Kerkennah — Pêche et ciel étoilé',
                        'description'=> 'Week-end de bivouac sur les îles Kerkennah. Traversée en ferry, camping sauvage, pêche traditionnelle avec les pêcheurs locaux et coucher de soleil sur la mer.',
                        'type'       => 'Bivouac désertique',
                        'activities' => ['bivouac', 'pêche', 'ferry', 'photographie'],
                        'start_date' => '2026-06-20',
                        'end_date'   => '2026-06-21',
                        'latitude'   => '34.7000',
                        'longitude'  => '11.5000',
                        'address'    => 'Iles Kerkennah, Sfax, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-04-25 11:00:00',
                    ],
                ],
            ],
            [
                'email' => 'trek.sud.tunisie@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Trek Matmata — Villages troglodytes',
                        'description'=> 'Trek de 3 jours dans les montagnes de Matmata à la découverte des villages berbères troglodytes. Paysages lunaires uniques, hospitalité authentique et cuisine locale.',
                        'type'       => 'Bivouac désertique',
                        'activities' => ['trek', 'village berbère', 'troglodyte', 'gastronomie locale'],
                        'start_date' => '2026-01-23',
                        'end_date'   => '2026-01-25',
                        'latitude'   => '33.5408',
                        'longitude'  => '9.9720',
                        'address'    => 'Matmata, Gabès, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-01-05 08:00:00',
                    ],
                    [
                        'title'      => 'Canyons de Tamerza — Oasis et cascades',
                        'description'=> 'Exploration des gorges et cascades de Tamerza, la plus grande oasis de montagne de Tunisie. Trek dans les canyons, baignade dans les bassins naturels et nuit à la belle étoile.',
                        'type'       => 'Voyage aventure',
                        'activities' => ['canyoning', 'cascade', 'oasis', 'bivouac'],
                        'start_date' => '2026-07-18',
                        'end_date'   => '2026-07-20',
                        'latitude'   => '34.3907',
                        'longitude'  => '7.9667',
                        'address'    => 'Tamerza, Tataouine, Tunisie',
                        'status'     => 'pending',
                        'created_at' => '2026-05-03 10:00:00',
                    ],
                ],
            ],
            [
                'email' => 'nature.lovers.sousse@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Boukornine — Parc national et panorama sur le Sahel',
                        'description'=> 'Randonnée dans le parc national de Boukornine au-dessus de Hammam Lif. Deux sommets, vue à 360° sur le golfe de Tunis. Flora méditerranéenne préservée.',
                        'type'       => 'Sortie nature',
                        'activities' => ['randonnée', 'botanique', 'panorama'],
                        'start_date' => '2025-12-06',
                        'end_date'   => '2025-12-06',
                        'latitude'   => '36.7833',
                        'longitude'  => '10.5500',
                        'address'    => 'Parc National de Boukornine, Ben Arous, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-11-22 09:00:00',
                    ],
                    [
                        'title'      => 'Camping convivial — Plage de Salakta',
                        'description'=> 'Week-end camping en bord de mer à Salakta. Plage tranquille, eau turquoise, barbecue collectif et veillée musicale. Ambiance décontractée pour familles et amis.',
                        'type'       => 'Camping familial',
                        'activities' => ['camping', 'baignade', 'barbecue', 'musique'],
                        'start_date' => '2026-08-07',
                        'end_date'   => '2026-08-09',
                        'latitude'   => '35.2867',
                        'longitude'  => '11.0342',
                        'address'    => 'Salakta Beach, Mahdia, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-05-08 11:30:00',
                    ],
                ],
            ],
            [
                'email' => 'mountain.climbers.beja@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Jebel Ghorra — Sommet de Béja',
                        'description'=> 'Ascension du Jebel Ghorra, point culminant de la région de Béja. Randonnée de 10 km avec 900m de dénivelé, faune rupestre et vue exceptionnelle sur les plaines de la Kroumirie.',
                        'type'       => 'Randonnée',
                        'activities' => ['escalade', 'randonnée technique', 'faune rupestre'],
                        'start_date' => '2025-09-20',
                        'end_date'   => '2025-09-20',
                        'latitude'   => '36.7261',
                        'longitude'  => '9.1811',
                        'address'    => 'Jebel Ghorra, Béja, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-09-01 08:00:00',
                    ],
                    [
                        'title'      => 'Bivouac Nefza — Forêt primaire et rivières',
                        'description'=> 'Bivouac au cœur de la forêt de Nefza. Randonnée le long des rivières, observation de la faune forestière et nuit sous les chênes-lièges centenaires.',
                        'type'       => 'Bivouac montagne',
                        'activities' => ['bivouac', 'forêt', 'rivière', 'faune forestière'],
                        'start_date' => '2026-05-23',
                        'end_date'   => '2026-05-24',
                        'latitude'   => '37.0314',
                        'longitude'  => '9.0422',
                        'address'    => 'Forêt de Nefza, Béja, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-04-15 10:00:00',
                    ],
                ],
            ],
            [
                'email' => 'bivouac.sahara@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Nuit des Sables — Douz, Porte du Sahara',
                        'description'=> 'Bivouac au cœur des dunes de Douz, la porte du grand Sahara tunisien. Coucher de soleil spectaculaire, dîner berbère traditionnel et nuit sous un ciel d\'une pureté absolue.',
                        'type'       => 'Bivouac désertique',
                        'activities' => ['dunes', 'astronomie', 'gastronomie berbère', 'dromadaire'],
                        'start_date' => '2026-02-13',
                        'end_date'   => '2026-02-14',
                        'latitude'   => '33.4567',
                        'longitude'  => '9.0233',
                        'address'    => 'Douz, Kébili, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-01-25 09:00:00',
                    ],
                    [
                        'title'      => 'Erg de Zaafrane — Dunes vierges',
                        'description'=> 'Expédition dans l\'erg de Zaafrane, à l\'écart des circuits touristiques. Trek de 2 jours à travers des dunes vierges, bivouac et lever de soleil depuis les crêtes de sable.',
                        'type'       => 'Bivouac désertique',
                        'activities' => ['trek', 'dunes', 'bivouac', 'désert sauvage'],
                        'start_date' => '2026-09-12',
                        'end_date'   => '2026-09-13',
                        'latitude'   => '33.2617',
                        'longitude'  => '9.1339',
                        'address'    => 'Erg de Zaafrane, Kébili, Tunisie',
                        'status'     => 'pending',
                        'created_at' => '2026-05-07 14:30:00',
                    ],
                ],
            ],
            [
                'email' => 'randonneurs.kairouan@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Jugurtha Tableland — Table des Géants',
                        'description'=> 'Ascension du Jugurtha Tableland, plateau basaltique perché à 1271m d\'altitude. Escaliers taillés dans la roche, vue imprenable et pique-nique au sommet.',
                        'type'       => 'Randonnée',
                        'activities' => ['escalade', 'randonnée historique', 'panorama'],
                        'start_date' => '2025-11-01',
                        'end_date'   => '2025-11-01',
                        'latitude'   => '35.5200',
                        'longitude'  => '8.4700',
                        'address'    => 'Jugurtha Tableland, Kef, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-10-15 09:30:00',
                    ],
                    [
                        'title'      => 'Makthar — Cité romaine et nature',
                        'description'=> 'Sortie découverte aux ruines de Makthar, l\'une des plus belles cités romaines de Tunisie. Randonnée autour du site, pique-nique et visite guidée. Niveau débutant, idéal en famille.',
                        'type'       => 'Sortie nature',
                        'activities' => ['archéologie', 'randonnée', 'histoire'],
                        'start_date' => '2026-06-27',
                        'end_date'   => '2026-06-27',
                        'latitude'   => '35.8567',
                        'longitude'  => '9.2022',
                        'address'    => 'Makthar, Siliana, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-04-30 10:00:00',
                    ],
                ],
            ],
            [
                'email' => 'coastal.explorers.mahdia@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Randonnée côtière — Mahdia, falaises et criques',
                        'description'=> 'Randonnée le long de la côte de Mahdia. Falaises de calcaire blanc, criques secrètes, villages de pêcheurs et couchers de soleil sur la mer Méditerranée.',
                        'type'       => 'Sortie nature',
                        'activities' => ['randonnée côtière', 'baignade', 'snorkeling', 'village pêcheurs'],
                        'start_date' => '2025-07-11',
                        'end_date'   => '2025-07-13',
                        'latitude'   => '35.5050',
                        'longitude'  => '11.0622',
                        'address'    => 'Mahdia, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-06-25 09:00:00',
                    ],
                    [
                        'title'      => 'Kayak de mer — Iles Kuriat et tortues marines',
                        'description'=> 'Expédition en kayak jusqu\'aux Iles Kuriat, réserve naturelle et site de ponte des tortues marines. Snorkeling en eaux cristallines et bivouac sur l\'île.',
                        'type'       => 'Voyage aventure',
                        'activities' => ['kayak', 'snorkeling', 'tortues marines', 'bivouac île'],
                        'start_date' => '2026-07-04',
                        'end_date'   => '2026-07-05',
                        'latitude'   => '35.7317',
                        'longitude'  => '11.1825',
                        'address'    => 'Iles Kuriat, Monastir, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-05-09 08:00:00',
                    ],
                ],
            ],
            [
                'email' => 'jeunesse.nature.monastir@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Camping jeunesse — Côte de Monastir',
                        'description'=> 'Week-end camping jeunesse sur la côte de Monastir. Tentes collectives, volleyball sur la plage et veillée musicale. Ouvert aux 16-30 ans.',
                        'type'       => 'Camping familial',
                        'activities' => ['camping', 'volleyball', 'baignade', 'musique'],
                        'start_date' => '2026-04-17',
                        'end_date'   => '2026-04-19',
                        'latitude'   => '35.7643',
                        'longitude'  => '10.8113',
                        'address'    => 'Plage de Monastir, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-03-20 10:00:00',
                    ],
                    [
                        'title'      => 'Thapsus — Histoire antique et mer',
                        'description'=> 'Découverte du site archéologique de Thapsus (Ras Dimas), là où César battit Pompée. Randonnée côtière, baignade et pique-nique historique pour les passionnés d\'histoire.',
                        'type'       => 'Sortie nature',
                        'activities' => ['archéologie', 'histoire', 'randonnée côtière', 'baignade'],
                        'start_date' => '2026-08-22',
                        'end_date'   => '2026-08-22',
                        'latitude'   => '35.7278',
                        'longitude'  => '10.8806',
                        'address'    => 'Ras Dimas, Mahdia, Tunisie',
                        'status'     => 'pending',
                        'created_at' => '2026-05-06 09:30:00',
                    ],
                ],
            ],
            [
                'email' => 'sahara.trek.gabes@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Réserve de Bou Hedma — Gazelles et addax',
                        'description'=> 'Safari-randonnée dans la réserve naturelle de Bou Hedma, dernier refuge de la flore sahélo-saharienne. Observation de gazelles, addax et fennecs dans leur habitat naturel.',
                        'type'       => 'Sortie nature',
                        'activities' => ['safari', 'observation faune', 'randonnée', 'photographie'],
                        'start_date' => '2025-11-29',
                        'end_date'   => '2025-11-30',
                        'latitude'   => '34.5833',
                        'longitude'  => '9.6333',
                        'address'    => 'Réserve de Bou Hedma, Gafsa, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-11-10 08:00:00',
                    ],
                    [
                        'title'      => 'Trek Matmata—Douz : La grande traversée',
                        'description'=> 'Trek de 4 jours de Matmata à Douz, traversant le Dahar et les premières dunes du Sahara. Bivouacs dans des paysages lunaires, rencontre avec les derniers nomades et arrivée à Douz.',
                        'type'       => 'Voyage aventure',
                        'activities' => ['trek multi-jours', 'bivouac', 'nomades', 'désert'],
                        'start_date' => '2026-03-14',
                        'end_date'   => '2026-03-17',
                        'latitude'   => '33.5408',
                        'longitude'  => '9.9720',
                        'address'    => 'Matmata, Gabès, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-02-15 10:00:00',
                    ],
                ],
            ],
            [
                'email' => 'aventuriers.kef@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Jebel Serdj — Toit du Centre tunisien',
                        'description'=> 'Ascension du Jebel Serdj (1357m), point culminant du centre de la Tunisie. Randonnée exigeante récompensée par une vue à 360°, du désert au nord au Tell.',
                        'type'       => 'Randonnée',
                        'activities' => ['randonnée technique', 'alpinisme', 'panorama'],
                        'start_date' => '2025-10-11',
                        'end_date'   => '2025-10-11',
                        'latitude'   => '35.8000',
                        'longitude'  => '9.3000',
                        'address'    => 'Jebel Serdj, Siliana, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-09-25 09:00:00',
                    ],
                    [
                        'title'      => 'Bivouac Aïn Draham — Nuit de Noël en forêt',
                        'description'=> 'Bivouac spécial de Noël dans la forêt d\'Aïn Draham. Peut-être un peu de neige, certainement du feu de camp, de la musique et une atmosphère magique sous les étoiles de la Kroumirie.',
                        'type'       => 'Bivouac montagne',
                        'activities' => ['bivouac hivernal', 'feu de camp', 'musique', 'neige possible'],
                        'start_date' => '2025-12-25',
                        'end_date'   => '2025-12-26',
                        'latitude'   => '36.7794',
                        'longitude'  => '8.6875',
                        'address'    => 'Aïn Draham, Jendouba, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-12-01 12:00:00',
                    ],
                ],
            ],
            [
                'email' => 'ecotourisme.jendouba@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Bulla Regia — Cité romaine souterraine',
                        'description'=> 'Visite des ruines de Bulla Regia, ville romaine unique avec ses maisons souterraines pour fuir la chaleur. Randonnée dans le site et pique-nique au bord de l\'oued.',
                        'type'       => 'Sortie nature',
                        'activities' => ['archéologie', 'histoire romaine', 'randonnée', 'pique-nique'],
                        'start_date' => '2025-12-13',
                        'end_date'   => '2025-12-13',
                        'latitude'   => '36.5556',
                        'longitude'  => '8.7639',
                        'address'    => 'Bulla Regia, Jendouba, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-11-30 09:00:00',
                    ],
                    [
                        'title'      => 'Parc de Bellif — Écotourisme et biodiversité',
                        'description'=> 'Journée écotouristique dans le parc de Bellif, réserve naturelle méconnue de la région de Jendouba. Randonnée guidée, atelier botanique et sensibilisation à la conservation.',
                        'type'       => 'Randonnée',
                        'activities' => ['écotourisme', 'botanique', 'conservation', 'randonnée guidée'],
                        'start_date' => '2026-05-16',
                        'end_date'   => '2026-05-16',
                        'latitude'   => '36.6700',
                        'longitude'  => '8.8200',
                        'address'    => 'Parc de Bellif, Jendouba, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-04-28 11:00:00',
                    ],
                ],
            ],
            [
                'email' => 'outdoor.club.siliana@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Jebel Bargou — Randonnée et eau de source',
                        'description'=> 'Randonnée autour du Jebel Bargou et sa source légendaire. Chemin de crête avec panorama sur la dorsale tunisienne, oliveraies centenaires et dégustation d\'eau de source au retour.',
                        'type'       => 'Randonnée',
                        'activities' => ['randonnée', 'source naturelle', 'panorama', 'oliviers'],
                        'start_date' => '2026-03-07',
                        'end_date'   => '2026-03-07',
                        'latitude'   => '36.0731',
                        'longitude'  => '9.5431',
                        'address'    => 'Jebel Bargou, Siliana, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-02-20 10:00:00',
                    ],
                    [
                        'title'      => 'Gorges de Siliana — Canyon et piscines naturelles',
                        'description'=> 'Exploration des gorges de l\'oued Siliana avec ses piscines naturelles creusées dans la roche. Randonnée aquatique et baignade en eau fraîche dans un cadre spectaculaire.',
                        'type'       => 'Sortie nature',
                        'activities' => ['canyoning', 'baignade', 'piscines naturelles', 'randonnée aquatique'],
                        'start_date' => '2026-07-25',
                        'end_date'   => '2026-07-25',
                        'latitude'   => '36.0844',
                        'longitude'  => '9.3747',
                        'address'    => 'Gorges de Siliana, Siliana, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2026-05-09 09:30:00',
                    ],
                ],
            ],
            [
                'email' => 'wilderness.tunis@gmail.com',
                'annonces' => [
                    [
                        'title'      => 'Bivouac Jebel Bou Kornine — Aux portes de Tunis',
                        'description'=> 'Bivouac au sommet du Jebel Bou Kornine, le gardien de Tunis. Randonnée nocturne aux étoiles, nuit en altitude et lever de soleil magique sur la capitale.',
                        'type'       => 'Bivouac montagne',
                        'activities' => ['bivouac', 'randonnée nocturne', 'lever de soleil', 'altitude'],
                        'start_date' => '2026-01-10',
                        'end_date'   => '2026-01-11',
                        'latitude'   => '36.7833',
                        'longitude'  => '10.5500',
                        'address'    => 'Jebel Bou Kornine, Ben Arous, Tunisie',
                        'status'     => 'approved',
                        'created_at' => '2025-12-22 10:00:00',
                    ],
                    [
                        'title'      => 'Randonnée Zaghouan — Source à source',
                        'description'=> 'Randonnée de source en source autour de Zaghouan, entre aqueducs romains et sources naturelles. Circuit de 15 km, paysages lunaires et rencontre avec les villageois.',
                        'type'       => 'Randonnée',
                        'activities' => ['randonnée', 'aqueduc romain', 'sources naturelles', 'patrimoine'],
                        'start_date' => '2026-09-19',
                        'end_date'   => '2026-09-19',
                        'latitude'   => '36.3972',
                        'longitude'  => '10.1086',
                        'address'    => 'Zaghouan, Tunisie',
                        'status'     => 'pending',
                        'created_at' => '2026-05-08 13:00:00',
                    ],
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 20 French top-level comment texts
    // ──────────────────────────────────────────────────────────────────────────
    private function topLevelComments(): array
    {
        return [
            "Super initiative ! J'ai hâte de participer à cette sortie, ça fait longtemps que j'en cherche une comme ça.",
            "Est-ce que les places sont encore disponibles ? Je voudrais venir avec un ami.",
            "Quel est le niveau physique requis ? Je débute en randonnée mais je suis motivé.",
            "J'y suis allé l'année dernière, c'est absolument magnifique ! Vous allez adorer cet endroit.",
            "Combien de kilomètres de marche au total ? Avec combien de dénivelé ?",
            "Est-ce qu'on peut venir avec des enfants de moins de 10 ans ?",
            "Vue incroyable depuis ce sommet, j'ai les meilleures photos de ma vie là-bas !",
            "Comment s'inscrire ? Je n'arrive pas à trouver le formulaire sur la page.",
            "Bravo pour l'organisation ! Votre groupe est vraiment professionnel et sérieux.",
            "Est-ce que le matériel de camping est fourni ou faut-il amener le sien ?",
            "J'ai participé à votre dernière sortie, c'était exceptionnel ! Je re-signe sans hésiter.",
            "Parfait pour ce week-end ! Je partage l'annonce avec mes amis du boulot.",
            "Quelle est la météo généralement dans cette région à cette période de l'année ?",
            "Y a-t-il des guides expérimentés qui accompagnent le groupe pendant toute la durée ?",
            "J'adore cette région, tellement sauvage et préservée. Merci de faire découvrir ces coins.",
            "Combien de personnes maximum dans le groupe ? Je veux m'assurer qu'il reste de la place.",
            "Très belle annonce ! C'est quoi le meilleur accès depuis Tunis en voiture ?",
            "C'est exactement ce que je cherchais pour ce week-end prolongé !",
            "Est-ce que le prix inclut le transport depuis la capitale ou c'est individuel ?",
            "Activité parfaite pour se déconnecter de la ville et retrouver la vraie nature !",
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10 French reply texts
    // ──────────────────────────────────────────────────────────────────────────
    private function replyComments(): array
    {
        return [
            "Oui, les inscriptions sont encore ouvertes ! Contactez-nous par message privé pour réserver votre place.",
            "Le niveau intermédiaire est recommandé, mais les débutants motivés et en bonne forme sont les bienvenus.",
            "Bien sûr ! Les enfants de plus de 8 ans peuvent participer accompagnés de leurs parents.",
            "Environ 12 km aller-retour avec 600m de dénivelé. Comptez 5 à 6h de marche au total.",
            "Le matériel de base (tente, sac de couchage) est fourni. Précisez vos besoins lors de l'inscription.",
            "Oui, deux guides certifiés accompagneront le groupe tout au long du parcours.",
            "Il reste encore 4 places disponibles, dépêchez-vous ! Premier arrivé, premier servi.",
            "La météo sera idéale pour cette période, pas trop chaud et ciel bien dégagé.",
            "Le transport depuis Tunis est inclus dans le prix. Départ prévu à 6h du matin.",
            "Merci pour votre enthousiasme ! On a vraiment hâte de vous accueillir dans notre groupe.",
        ];
    }
}
