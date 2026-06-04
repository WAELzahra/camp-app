<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FixCampingZonesSeeder extends Seeder
{
    public function run(): void
    {
        $files = [
            base_path('tunisia_wild_camping_zones.json'),
            base_path('tunisia_wild_camping_zones_lot2.json'),
        ];

        $zones = [];
        $seen = [];

        foreach ($files as $file) {
            if (! File::exists($file)) {
                $this->command?->warn("Fichier introuvable : {$file}");
                continue;
            }

            $data = json_decode(File::get($file), true);

            if (! is_array($data)) {
                $this->command?->warn("JSON invalide : {$file}");
                continue;
            }

            foreach ($data as $item) {
                $nom = trim((string) ($item['name'] ?? ''));
                $region = trim((string) ($item['region'] ?? ''));
                $description = trim((string) ($item['description'] ?? ''));

                $coords = $item['coordinates'] ?? $item['coordonnees'] ?? [];
                $lat = $coords['lat'] ?? null;
                $lng = $coords['lng'] ?? $coords['lon'] ?? null;

                if ($nom === '' || $region === '' || $description === '' || $lat === null || $lng === null) {
                    continue;
                }

                $uniqueKey = Str::lower($nom . '|' . $region . '|' . $lat . '|' . $lng);
                if (isset($seen[$uniqueKey])) {
                    continue;
                }
                $seen[$uniqueKey] = true;

                $type = trim((string) ($item['type'] ?? 'nature'));
                $acces = trim((string) ($item['acces'] ?? ''));
                $meilleureSaison = $item['meilleure_saison'] ?? null;
                $difficulteAcces = $item['difficulte_acces'] ?? null;

                $city = $this->guessCity($nom, $region);
                $terrain = $this->terrainFromType($type);
                $difficulty = $this->difficultyFromRealValue($difficulteAcces, $type);
                $accessType = $this->accessTypeFromText($acces, $difficulty, $type);
                $bestSeason = $this->bestSeasonFromRealValue($meilleureSaison, $type);
                $activities = $this->activitiesFromRealData($item, $type, $description);
                $facilities = $this->facilitiesFromRealData($item);
                $rules = $this->rulesFromRealData($type, (bool)($item['eau_disponible'] ?? false), (bool)($item['baignade'] ?? false));
                $dangerLevel = $this->dangerLevel($difficulty, $type);

                // ── AI fields consumed by the trip planner / gear / safety engines ──
                $terrainType            = $this->terrainTypeFromType($type, $description);
                $isBeginnerFriendly     = $difficulty === 'easy' && $dangerLevel === 'low';
                [$minTemp, $maxTemp]    = $this->tempRangeFromTerrain($terrainType);

                $zones[] = [
                    'nom'               => $nom,
                    'city'              => $city,
                    'region'            => $region,
                    'commune'           => $city,
                    'description'       => Str::limit($description, 240, ''),
                    'full_description'  => $description,
                    'terrain'           => $terrain,
                    'difficulty'        => $difficulty,
                    'lat'               => (float) $lat,
                    'lng'               => (float) $lng,
                    'adresse'           => $acces !== '' ? $acces : $nom . ', ' . $region . ', Tunisie',
                    'distance'          => null,
                    'altitude'          => null,
                    'access_type'       => $accessType,
                    'accessibility'     => $acces !== '' ? $acces : $this->defaultAccessibility($accessType, $difficulty),
                    'rating'            => $this->ratingFromDifficulty($difficulty),
                    'reviews_count'     => $this->reviewsCountFromId((int) ($item['id'] ?? count($zones) + 1)),
                    'best_season'       => json_encode($bestSeason, JSON_UNESCAPED_UNICODE),
                    'activities'        => json_encode($activities, JSON_UNESCAPED_UNICODE),
                    'facilities'        => json_encode($facilities, JSON_UNESCAPED_UNICODE),
                    'rules'             => json_encode($rules, JSON_UNESCAPED_UNICODE),
                    'contact_phone'     => null,
                    'contact_email'     => null,
                    'contact_website'   => null,
                    'is_public'         => true,
                    'status'            => true,
                    'is_protected_area' => $this->isProtectedArea($nom, $description),
                    'is_closed'         => false,
                    'danger_level'      => $dangerLevel,
                    'is_beginner_friendly' => $isBeginnerFriendly,
                    'terrain_type'      => $terrainType,
                    'min_temp_celsius'  => $minTemp,
                    'max_temp_celsius'  => $maxTemp,
                    'max_capacity'      => $this->capacityFromType($type, $difficulty),
                    'map_zoom_level'    => $this->zoomFromType($type),
                    'source'            => 'real_json_data',
                    'centre_id'         => null,
                    'added_by'          => null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('camping_zones')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        foreach (array_chunk($zones, 50) as $chunk) {
            DB::table('camping_zones')->insert($chunk);
        }

        $this->command?->info('✅ ' . count($zones) . ' zones réelles insérées dans camping_zones.');
    }

    private function guessCity(string $nom, string $region): string
    {
        $clean = str_replace(['–', '—'], '-', $nom);
        $parts = array_map('trim', explode('-', $clean));

        foreach (array_reverse($parts) as $part) {
            if ($part !== '' && mb_strlen($part) <= 35 && ! preg_match('/^(rive|zone|versant|plage|forêt|jebel|djebel|lac|barrage|oasis|gorges|cap|piste)/iu', $part)) {
                return $part;
            }
        }

        return $region;
    }

    private function terrainFromType(string $type): string
    {
        return match ($type) {
            'beach', 'plage' => 'Plage',
            'lake', 'lac' => 'Lac / Barrage',
            'forest', 'foret' => 'Forêt',
            'mountain', 'montagne' => 'Montagne',
            'desert' => 'Désert',
            'foret_plage' => 'Forêt et plage',
            'plage_montagne' => 'Plage et montagne',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Canonical terrain_type consumed by the AI engines.
     * Enum: forest | mountain | desert | coastal | plain | wetland.
     */
    private function terrainTypeFromType(string $type, string $description = ''): string
    {
        $mapped = match ($type) {
            'beach', 'plage', 'foret_plage', 'plage_montagne' => 'coastal',
            'lake', 'lac'                                      => 'wetland',
            'forest', 'foret'                                 => 'forest',
            'mountain', 'montagne'                            => 'mountain',
            'desert'                                          => 'desert',
            default                                           => null,
        };
        if ($mapped !== null) {
            return $mapped;
        }

        // Infer from description when the source type is generic ("nature").
        $text = Str::lower($description);
        return match (true) {
            str_contains($text, 'désert') || str_contains($text, 'desert') || str_contains($text, 'dune') || str_contains($text, 'sahara') => 'desert',
            str_contains($text, 'plage') || str_contains($text, 'mer') || str_contains($text, 'côt') || str_contains($text, 'cap ')          => 'coastal',
            str_contains($text, 'montagne') || str_contains($text, 'jebel') || str_contains($text, 'djebel') || str_contains($text, 'sommet') => 'mountain',
            str_contains($text, 'lac') || str_contains($text, 'barrage') || str_contains($text, 'oued') || str_contains($text, 'zone humide') => 'wetland',
            str_contains($text, 'forêt') || str_contains($text, 'foret') || str_contains($text, 'bois') || str_contains($text, 'chêne')       => 'forest',
            default => 'plain',
        };
    }

    /**
     * Indicative seasonal temperature band (°C) per terrain, used as planner
     * metadata and gear/safety hints.
     *
     * @return array{0:int,1:int} [min, max]
     */
    private function tempRangeFromTerrain(string $terrainType): array
    {
        return match ($terrainType) {
            'desert'   => [6, 42],
            'coastal'  => [13, 34],
            'wetland'  => [8, 32],
            'forest'   => [3, 29],
            'mountain' => [0, 26],
            default    => [7, 36], // plain
        };
    }

    private function difficultyFromRealValue(?string $difficulteAcces, string $type): string
    {
        $value = Str::lower((string) $difficulteAcces);

        if (str_contains($value, 'facile')) return 'easy';
        if (str_contains($value, 'modérée') || str_contains($value, 'modere') || str_contains($value, 'modéré')) return 'medium';
        if (str_contains($value, 'difficile')) return 'hard';
        if (str_contains($value, 'expédition') || str_contains($value, 'expedition')) return 'hard';

        return in_array($type, ['desert', 'mountain', 'montagne'], true) ? 'medium' : 'easy';
    }

    private function accessTypeFromText(string $acces, string $difficulty, string $type): string
    {
        $text = Str::lower($acces);

        if (str_contains($text, 'barque') || str_contains($text, 'bateau') || str_contains($text, 'boat')) return 'boat';
        if (str_contains($text, 'à pied') || str_contains($text, 'a pied') || str_contains($text, 'marche') || str_contains($text, 'sentier')) return 'trail';
        if (str_contains($text, '4x4') || str_contains($text, 'piste')) return 'mixed';
        if (str_contains($text, 'route')) return 'road';

        return in_array($type, ['desert'], true) ? 'mixed' : 'road';
    }

    private function bestSeasonFromRealValue(?string $value, string $type): array
    {
        if ($value) {
            return array_map('trim', preg_split('/[,؛،]+/', $value));
        }

        return match ($type) {
            'desert' => ['Octobre–Mars'],
            'beach', 'plage', 'foret_plage', 'plage_montagne' => ['Mai–Octobre'],
            'mountain', 'montagne', 'forest', 'foret' => ['Mars–Juin', 'Septembre–Novembre'],
            'lake', 'lac' => ['Octobre–Avril'],
            default => ['Printemps', 'Automne'],
        };
    }

    private function activitiesFromRealData(array $item, string $type, string $description): array
    {
        $activities = ['Camping', 'Randonnée', 'Photographie'];
        $text = Str::lower($description . ' ' . $type);

        if (($item['baignade'] ?? false) || str_contains($text, 'plage') || str_contains($text, 'mer')) $activities[] = 'Baignade';
        if (str_contains($text, 'oiseau') || str_contains($text, 'flamant') || str_contains($text, 'ornithologique')) $activities[] = 'Observation des oiseaux';
        if (str_contains($text, 'pêche') || str_contains($text, 'peche')) $activities[] = 'Pêche';
        if (str_contains($text, 'grotte') || str_contains($text, 'canyon') || str_contains($text, 'gorge')) $activities[] = 'Exploration naturelle';
        if (str_contains($text, 'désert') || str_contains($text, 'desert') || str_contains($text, 'dune')) $activities[] = 'Bivouac désertique';
        if (str_contains($text, 'source') || str_contains($text, 'thermale') || str_contains($text, 'cascade')) $activities[] = 'Découverte des sources naturelles';

        return array_values(array_unique($activities));
    }

    private function facilitiesFromRealData(array $item): array
    {
        $facilities = ['Zone naturelle', 'Aire de bivouac'];

        if ($item['eau_disponible'] ?? false) $facilities[] = 'Eau disponible';
        if ($item['ombre'] ?? false) $facilities[] = 'Ombre naturelle';
        if ($item['baignade'] ?? false) $facilities[] = 'Zone de baignade';

        return $facilities;
    }

    private function rulesFromRealData(string $type, bool $water, bool $swimming): array
    {
        $rules = [
            'Respecter la nature et ne laisser aucun déchet',
            'Éviter les feux hors zones autorisées',
            'Respecter la faune, la flore et les habitants locaux',
        ];

        if ($water) $rules[] = 'Préserver les points d\'eau et éviter toute pollution';
        if ($swimming) $rules[] = 'Se baigner uniquement si les conditions sont sûres';
        if (in_array($type, ['desert', 'mountain', 'montagne'], true)) $rules[] = 'Prévoir un équipement adapté et informer quelqu\'un de l\'itinéraire';

        return $rules;
    }

    private function defaultAccessibility(string $accessType, string $difficulty): string
    {
        return match ($accessType) {
            'boat'  => 'Accès par bateau ou barque locale',
            'trail' => 'Accès à pied par sentier naturel',
            'mixed' => 'Accès par piste ou 4x4 recommandé',
            default => $difficulty === 'easy' ? 'Accès relativement facile' : 'Accès naturel nécessitant prudence',
        };
    }

    private function ratingFromDifficulty(string $difficulty): float
    {
        return match ($difficulty) {
            'easy' => 4.4,
            'medium' => 4.5,
            'hard' => 4.6,
            'expert' => 4.7,
            default => 4.3,
        };
    }

    private function reviewsCountFromId(int $id): int
    {
        return 20 + ($id % 85);
    }

    private function dangerLevel(string $difficulty, string $type): string
    {
        if ($difficulty === 'hard' || $type === 'desert') return 'moderate';
        return 'low';
    }

    private function isProtectedArea(string $nom, string $description): bool
    {
        $text = Str::lower($nom . ' ' . $description);
        return str_contains($text, 'parc')
            || str_contains($text, 'réserve')
            || str_contains($text, 'reserve')
            || str_contains($text, 'unesco')
            || str_contains($text, 'protégée')
            || str_contains($text, 'protected');
    }

    private function capacityFromType(string $type, string $difficulty): int
    {
        if ($difficulty === 'expert') return 10;

        return match ($type) {
            'beach', 'plage' => 60,
            'lake', 'lac' => 45,
            'forest', 'foret' => 40,
            'mountain', 'montagne' => 25,
            'desert' => 20,
            'foret_plage', 'plage_montagne' => 35,
            default => 30,
        };
    }

    private function zoomFromType(string $type): int
    {
        return match ($type) {
            'beach', 'plage' => 14,
            'lake', 'lac' => 13,
            'forest', 'foret' => 13,
            'mountain', 'montagne' => 12,
            'desert' => 11,
            default => 13,
        };
    }
}
