<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbacksAndFavoritesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('favorites')->truncate();
        DB::table('feedbacks')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $campeurRoleId = DB::table('roles')->where('name', 'campeur')->value('id');
        $centreRoleId  = DB::table('roles')->where('name', 'centre')->value('id');

        $campeurs = DB::table('users')
            ->where('role_id', $campeurRoleId)
            ->select('id', 'first_name', 'last_name', 'email', 'preferences')
            ->get()
            ->map(function ($u) {
                $u->archetype = json_decode($u->preferences, true)['archetype'] ?? 'C';
                return $u;
            });

        // ── Lookup data ───────────────────────────────────────────────────────
        $zones = DB::table('camping_zones')
            ->select('id', 'terrain', 'difficulty')
            ->get();

        $materielles = DB::table('materielles')
            ->select('id', 'fournisseur_id', 'nom')
            ->get();

        $finishedEvents = DB::table('events')
            ->where('status', 'finished')
            ->select('id', 'group_id', 'title', 'difficulty')
            ->get();

        $centreUserIds = DB::table('users')
            ->where('role_id', $centreRoleId)
            ->pluck('id')
            ->all();

        // ─────────────────────────────────────────────────────────────────────
        // FEEDBACKS
        // ─────────────────────────────────────────────────────────────────────
        $feedbackRows = [];

        // 1. Zone feedbacks (~40)
        $this->buildZoneFeedbacks($campeurs, $zones, $feedbackRows, $now);

        // 2. Materielle feedbacks (~25)
        $this->buildMaterielleefbacks($campeurs, $materielles, $feedbackRows, $now);

        // 3. Event / groupe feedbacks (~25)
        $this->buildEventFeedbacks($campeurs, $finishedEvents, $feedbackRows, $now);

        foreach (array_chunk($feedbackRows, 50) as $chunk) {
            DB::table('feedbacks')->insert($chunk);
        }
        $this->command?->info('✅ ' . count($feedbackRows) . ' feedbacks inserted.');

        // ─────────────────────────────────────────────────────────────────────
        // FAVORITES
        // ─────────────────────────────────────────────────────────────────────
        $favoriteRows = [];
        $seen         = [];

        foreach ($campeurs as $c) {
            $arch = $c->archetype;

            // 2–3 zone favorites matching archetype terrain
            $matchingZones = $this->archetypeZones($zones, $arch);
            $zoneCount     = min(($c->id % 2) + 2, $matchingZones->count());
            $zonePick      = $matchingZones->shuffle()->take($zoneCount);

            foreach ($zonePick as $z) {
                $key = $c->id . '_zone_' . $z->id;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $favoriteRows[] = [
                    'user_id'          => $c->id,
                    'favoritable_id'   => $z->id,
                    'favoritable_type' => 'zone',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            // 1–2 equipment favorites matching archetype
            $matCats      = $this->archetypeMatCats($arch);
            $matchingMats = $materielles->filter(fn($m) =>
                collect($matCats)->contains(fn($cat) => str_contains(strtolower($m->nom), strtolower($cat)))
            );
            if ($matchingMats->isEmpty()) $matchingMats = $materielles;

            $matCount = ($c->id % 2) + 1;
            $matPick  = $matchingMats->shuffle()->take($matCount);

            foreach ($matPick as $m) {
                $key = $c->id . '_equipment_' . $m->id;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $favoriteRows[] = [
                    'user_id'          => $c->id,
                    'favoritable_id'   => $m->id,
                    'favoritable_type' => 'equipment',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            // 1 centre favorite (every other campeur)
            if ($c->id % 2 === 0 && ! empty($centreUserIds)) {
                $centreId = $centreUserIds[$c->id % count($centreUserIds)];
                $key      = $c->id . '_centre_' . $centreId;
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $favoriteRows[] = [
                        'user_id'          => $c->id,
                        'favoritable_id'   => $centreId,
                        'favoritable_type' => 'centre',
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($favoriteRows, 50) as $chunk) {
            DB::table('favorites')->insert($chunk);
        }
        $this->command?->info('✅ ' . count($favoriteRows) . ' favorites inserted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Zone feedbacks — archetype-consistent notes
    // ─────────────────────────────────────────────────────────────────────────
    private function buildZoneFeedbacks(
        \Illuminate\Support\Collection $campeurs,
        \Illuminate\Support\Collection $zones,
        array &$rows,
        string $now
    ): void {
        $target   = 40;
        $added    = 0;

        foreach ($campeurs as $c) {
            if ($added >= $target) break;
            if ($c->id % 2 === 0) continue; // take ~half the campeurs

            $arch          = $c->archetype;
            $matchingZones = $this->archetypeZones($zones, $arch);
            if ($matchingZones->isEmpty()) $matchingZones = $zones;

            $zone = $matchingZones->values()->get($c->id % $matchingZones->count());
            if (! $zone) continue;

            $rows[] = [
                'user_id'          => $c->id,
                'target_id'        => null,
                'event_id'         => null,
                'zone_id'          => $zone->id,
                'materielle_id'    => null,
                'contenu'          => $this->zoneReviewText($arch, $zone->difficulty ?? 'easy'),
                'response'         => null,
                'note'             => $this->zoneNote($arch, $zone->difficulty ?? 'easy'),
                'type'             => 'zone',
                'status'           => 'approved',
                'rejection_reason' => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            $added++;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Materielle feedbacks
    // ─────────────────────────────────────────────────────────────────────────
    private function buildMaterielleefbacks(
        \Illuminate\Support\Collection $campeurs,
        \Illuminate\Support\Collection $materielles,
        array &$rows,
        string $now
    ): void {
        if ($materielles->isEmpty()) return;

        $target = 25;
        $added  = 0;
        $texts  = $this->materielleReviewTexts();
        $notes  = [4, 4, 5, 5, 5, 4, 3, 5, 4, 5];

        foreach ($campeurs as $i => $c) {
            if ($added >= $target) break;
            if ($i % 3 !== 0) continue;

            $mat = $materielles->values()->get($c->id % $materielles->count());
            if (! $mat) continue;

            $rows[] = [
                'user_id'          => $c->id,
                'target_id'        => $mat->fournisseur_id,
                'event_id'         => null,
                'zone_id'          => null,
                'materielle_id'    => $mat->id,
                'contenu'          => $texts[$c->id % count($texts)],
                'response'         => null,
                'note'             => $notes[$c->id % count($notes)],
                'type'             => 'materielle',
                'status'           => 'approved',
                'rejection_reason' => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            $added++;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Event / groupe feedbacks
    // ─────────────────────────────────────────────────────────────────────────
    private function buildEventFeedbacks(
        \Illuminate\Support\Collection $campeurs,
        \Illuminate\Support\Collection $finishedEvents,
        array &$rows,
        string $now
    ): void {
        if ($finishedEvents->isEmpty()) return;

        $target = 25;
        $added  = 0;
        $texts  = $this->eventReviewTexts();
        $notes  = [5, 4, 5, 4, 5, 5, 3, 4, 5, 4];

        foreach ($campeurs as $i => $c) {
            if ($added >= $target) break;
            if ($i % 3 !== 1) continue;

            $event = $finishedEvents->values()->get($c->id % $finishedEvents->count());
            if (! $event) continue;

            $rows[] = [
                'user_id'          => $c->id,
                'target_id'        => $event->group_id,
                'event_id'         => $event->id,
                'zone_id'          => null,
                'materielle_id'    => null,
                'contenu'          => $texts[$c->id % count($texts)],
                'response'         => null,
                'note'             => $notes[$c->id % count($notes)],
                'type'             => 'groupe',
                'status'           => 'approved',
                'rejection_reason' => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            $added++;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function archetypeZones(
        \Illuminate\Support\Collection $zones,
        string $arch
    ): \Illuminate\Support\Collection {
        $terrains = match ($arch) {
            'A'     => ['Montagne', 'Forêt', 'Lac / Barrage'],
            'B'     => ['Plage', 'Forêt et plage', 'Plage et montagne'],
            'C'     => ['Forêt', 'Montagne', 'Lac / Barrage'],
            'D'     => ['Désert'],
            'E'     => ['Plage', 'Forêt et plage'],
            'F'     => ['Forêt', 'Plage', 'Lac / Barrage'],
            default => ['Forêt', 'Montagne'],
        };

        $filtered = $zones->filter(fn($z) => in_array($z->terrain, $terrains));
        return $filtered->isNotEmpty() ? $filtered : $zones;
    }

    private function archetypeMatCats(string $arch): array
    {
        return match ($arch) {
            'A'     => ['tente légère', 'sac à dos', 'frontale', 'bivouac'],
            'B'     => ['familiale', 'cuisine', 'glacière', 'table'],
            'C'     => ['tente', 'sac à dos', 'réchaud', 'frontale'],
            'D'     => ['GPS', 'survie', 'désert', 'filtre'],
            'E'     => ['bell tent', 'lodge', 'mobilier', 'hamac'],
            'F'     => ['sac de couchage', 'tapis', 'lampe'],
            default => ['tente', 'sac'],
        };
    }

    private function zoneNote(string $arch, string $difficulty): int
    {
        // A/D: high notes on hard/expert zones, lower on easy
        // B/E: high notes on easy zones, lower on hard
        return match ($arch) {
            'A', 'D' => match ($difficulty) {
                'hard', 'expert'  => rand(4, 5),
                'medium'          => rand(4, 5),
                default           => rand(3, 4),
            },
            'B', 'E' => match ($difficulty) {
                'easy'            => rand(4, 5),
                'medium'          => rand(3, 4),
                default           => rand(2, 3),
            },
            default => rand(3, 5),
        };
    }

    private function zoneReviewText(string $arch, string $difficulty): string
    {
        $reviews = [
            'A' => [
                'Zone exceptionnelle pour les randonneurs expérimentés. Sentiers bien tracés et vues panoramiques à couper le souffle. Je recommande vivement.',
                'Terrain exigeant mais magnifique. Bivouac sous les étoiles mémorable, loin de toute civilisation. Expérience inoubliable.',
                'Difficile d\'accès mais ça vaut vraiment le détour. Nature parfaitement préservée et sauvage. Retour prévu dès que possible.',
                'Site idéal pour le bivouac solo. Pas de foule, nature authentique et sentiers bien marqués. Coup de cœur absolu.',
            ],
            'B' => [
                'Zone parfaite pour les familles avec enfants. Accès facile, plage propre et espace sûr pour les petits. On reviendra l\'an prochain.',
                'Excellent site pour un camping en famille. Infrastructure correcte, bonne sécurité et ambiance conviviale. Très satisfaits.',
                'Très bel endroit pour un week-end avec la famille. Eau propre, ombre disponible et activités pour tous les âges.',
                'Site bien aménagé et sécurisé. Les enfants ont adoré la baignade et les jeux de plage. Recommandé pour les familles.',
            ],
            'C' => [
                'Belle forêt avec des randonnées variées et bien signalisées. Idéal pour un weekend nature en couple ou entre amis.',
                'Zone bien préservée avec une faune et flore remarquables. Cadre naturel magnifique, loin du bruit de la ville.',
                'Parfait pour un weekend de décompression. Sentiers accessibles, paysages variés et air pur garanti.',
                'Site magnifique pour la photographie nature. Lumière superbe en fin de journée. On y retourne régulièrement.',
            ],
            'D' => [
                'Paysage lunaire absolument à couper le souffle. Bivouac sous un ciel étoilé parfait, sans aucune pollution lumineuse.',
                'Expérience unique dans le Sahara tunisien. Authenticité garantie, chaleur le jour et fraîcheur la nuit. Magique.',
                'Zone désertique authentique, loin de tout. Navigation GPS indispensable. Pour aventuriers expérimentés seulement.',
                'Dunes magnifiques et silence absolu. On comprend pourquoi le désert fascine autant. Bivouac inoubliable.',
            ],
            'E' => [
                'Site magnifique avec une vue splendide sur la mer. Accès confortable et environnement parfait pour un séjour romantique.',
                'Très bel emplacement avec tous les équipements nécessaires. Idéal pour un glamping de qualité en couple.',
                'Cadre exceptionnel et paisible. Les couchers de soleil ici sont parmi les plus beaux que j\'ai vus. Parfait.',
                'Endroit idéal pour se ressourcer. Nature magnifique, confort correct et ambiance apaisante. Je recommande.',
            ],
            'F' => [
                'Super sortie avec les copains ! Zone accessible et ambiance parfaite pour le camping de groupe entre étudiants.',
                'On a passé une excellente nuit ici. Idéal pour un budget étudiant, nature accessible et facile à atteindre.',
                'Bonne zone pour débuter le camping. Pas trop difficile d\'accès et belle nature. On a adoré l\'expérience.',
                'Site sympa pour une sortie entre amis. Feu de camp, jeux et bonne humeur garantis. On repart avec de bons souvenirs.',
            ],
        ];

        $pool = $reviews[$arch] ?? $reviews['C'];
        return $pool[array_rand($pool)];
    }

    private function materielleReviewTexts(): array
    {
        return [
            'Matériel en excellent état, parfaitement conforme à la description. Fournisseur sérieux et livraison rapide. Je recommande vivement.',
            'Tente de très bonne qualité, montage facile en moins de 5 minutes. Imperméable et résistante au vent. Excellent achat.',
            'GPS très précis, indispensable pour nos randonnées dans le désert. Interface intuitive et batterie longue durée. Top produit.',
            'Réchaud performant, chauffe rapidement et consomme peu de gaz. Compact et léger, parfait pour le camping. Très satisfait.',
            'Sac à dos confortable et spacieux, dos bien ventilé. Idéal pour les longues randonnées. Fournisseur très professionnel.',
            'Sac de couchage chaud et léger, parfait pour les nuits fraîches de montagne. Rapport qualité-prix excellent.',
            'Lampe frontale très puissante, éclairage remarquable. Autonomie conforme aux annonces. Produit de qualité professionnelle.',
            'Kit de survie bien fourni et bien organisé. Tout était en parfait état. Fournisseur réactif et de confiance.',
            'Matelas de camping confortable, isolation du sol efficace. Léger et compact pour le transport. Très bon produit.',
            'Lanterne LED avec une belle diffusion lumineuse. Autonomie impressionnante et design solide. Je rachèterai sans hésiter.',
            'Kayak gonflable stable et facile à gonfler. Navigation agréable sur les eaux calmes du littoral tunisien. Super expérience.',
            'Bâtons de randonnée solides et bien réglables. Poignées antidérapantes très confortables. Fournisseur sérieux et ponctuel.',
        ];
    }

    private function eventReviewTexts(): array
    {
        return [
            'Organisation irréprochable du début à la fin. Guide compétent, programme bien structuré et très bonne ambiance dans le groupe.',
            'Événement parfaitement organisé, timing respecté et itinéraire magnifique. On reviendra sans hésiter pour la prochaine sortie.',
            'Superbe expérience ! L\'équipe organisatrice était professionnelle et attentionnée. Bravo pour cet événement de qualité.',
            'Trek magnifique avec un programme bien conçu et adapté à tous les niveaux. Guide expert et passionné. Merci pour cette sortie.',
            'Sortie nature exceptionnelle, bien encadrée et sécurisée. J\'ai découvert des paysages tunisiens que je ne connaissais pas.',
            'Groupe sympa, guide agréable et parcours superbe. Tout était bien préparé : repas, matériel et hébergement. Parfait.',
            'Bonne organisation générale. Quelques petits imprévus mais gérés avec professionnalisme. Je recommande ce club.',
            'Événement bien pensé avec une belle diversité d\'activités. Parfait pour découvrir la nature tunisienne en toute sécurité.',
            'Guide passionné et très connaisseur du terrain. Anecdotes historiques et naturelles enrichissantes. Vraiment top !',
            'Sortie bien organisée malgré les conditions météo changeantes. L\'équipe a su s\'adapter. Bravo et merci pour cette aventure.',
            'Programme ambitieux mais réalisable. On a été poussés à nous dépasser dans une bonne ambiance. Expérience enrichissante.',
            'Bon rapport qualité-prix pour cette sortie camping. Logistique bien rodée et guide sympa. Je le recommande aux débutants.',
        ];
    }
}
