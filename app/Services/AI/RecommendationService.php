<?php

namespace App\Services\AI;

use App\Models\Feedback;
use App\Models\Profile;
use App\Models\ProfileCampeur;
use Illuminate\Support\Collection;

/**
 * Cosine-similarity recommendation engine.
 *
 * Zones and gear items are scored by computing the cosine similarity between a
 * user feature vector and an item feature vector. Weights emerge from the
 * behavioral + static profile data — not from arbitrary fixed numbers.
 *
 * ─── Zone scoring ────────────────────────────────────────────────────────────
 * final_score = (cosine_similarity * 0.7) + (collaborative_normalized * 0.3)
 *
 * User zone vector (6 dimensions, all 0-1):
 *   [0] skill_encoded          beginner=0.0  intermediate=0.33  advanced=0.67  expert=1.0
 *   [1] budget_encoded         budget=0.0    moderate=0.5       premium=1.0
 *   [2] terrain_weight         1.0 if behavioral inferred terrain exists | 0.5 if static styles | 0.0
 *   [3] activity_richness      count(preferred_activities) / 10, capped at 1.0
 *   [4] experience_weight      total_trips / 20, capped at 1.0  (group_size proxy if trips null)
 *   [5] confidence_weight      behavioral_confidence attribute (0.0 for static-only profiles)
 *
 * Zone vector (6 dimensions, all 0-1):
 *   [0] difficulty_encoded     easy=0.0  medium=0.5  hard=1.0
 *   [1] rating_value           rating / 5.0
 *   [2] terrain_match          1.0 exact behavioral | 0.5 in static styles | 0.0
 *   [3] activity_overlap       Jaccard(user_activities, zone_activities)
 *   [4] accessibility_score    (is_beginner_friendly ? 1.0 : 0.5) × (1 - difficulty_encoded)
 *   [5] social_proof           (rating/5) × min(reviews_count/50, 1)
 *
 * ─── Gear scoring ────────────────────────────────────────────────────────────
 * User gear vector (4 dimensions):
 *   [0] terrain_affinity       same as zone user dim 2
 *   [1] budget_encoded         same as zone user dim 1
 *   [2] skill_encoded          same as zone user dim 0
 *   [3] experience_weight      same as zone user dim 4
 *
 * Gear item vector (4 dimensions):
 *   [0] terrain_match          1.0 exact tag match | 0.5 partial tag/style match | 0.0
 *   [1] price_normalized       tarif_nuit / max_tarif_in_collection
 *   [2] condition_encoded      new=1.0  like_new=0.75  good=0.5  fair=0.25
 *   [3] availability_score     is_rentable ? 1.0 : 0.5  (quantite_dispo not in select)
 */
class RecommendationService
{
    private const SKILL_ENC  = ['beginner' => 0.0, 'intermediate' => 0.33, 'advanced' => 0.67, 'expert' => 1.0];
    private const BUDGET_ENC = ['budget' => 0.0, 'moderate' => 0.5, 'premium' => 1.0];
    private const DIFF_ENC   = ['easy' => 0.0, 'medium' => 0.5, 'hard' => 1.0];
    private const COND_ENC   = ['new' => 1.0, 'like_new' => 0.75, 'good' => 0.5, 'fair' => 0.25];

    /**
     * Maps each English terrain_type to the French + English keywords campers
     * actually use in their preferred_trip_styles. Without this, a camper who
     * likes "montagne" never matches a "mountain" zone (str_contains fails
     * across languages), so terrain personalisation silently collapses to 0.
     */
    private const TERRAIN_SYNONYMS = [
        'mountain' => ['mountain', 'montagne', 'montagnes', 'massif', 'jebel', 'sommet', 'alpin'],
        'forest'   => ['forest', 'forêt', 'foret', 'bois', 'sylvestre', 'kroumirie'],
        'desert'   => ['desert', 'désert', 'sahara', 'dune', 'dunes', 'oasis', 'saharien'],
        'coastal'  => ['coastal', 'côtier', 'cotier', 'plage', 'mer', 'littoral', 'marin', 'baignade', 'côte'],
        'plain'    => ['plain', 'plaine', 'prairie', 'champ', 'campagne'],
        'wetland'  => ['wetland', 'humide', 'lac', 'marais', 'marécage', 'étang', 'lagune', 'sebkha'],
    ];

    // ── Public API (signatures unchanged) ────────────────────────────────────

    public function scoreZones(ProfileCampeur $profile, Collection $zones): Collection
    {
        $preferredActivities = $this->toArray($profile->preferred_activities);
        $preferredStyles     = $this->toArray($profile->preferred_trip_styles);
        $inferredTerrain     = $profile->getAttribute('inferred_terrain_preference');

        // ── Collaborative filtering: identical to prior implementation ────────
        $similarProfileIds = ProfileCampeur::where('skill_level', $profile->skill_level)
            ->where('budget_range', $profile->budget_range)
            ->where('profile_id', '!=', $profile->profile_id)
            ->pluck('profile_id');

        $similarUserIds = Profile::whereIn('id', $similarProfileIds)->pluck('user_id');

        $zoneFeedbacks = Feedback::whereIn('user_id', $similarUserIds)
            ->whereNotNull('zone_id')
            ->where('status', 'approved')
            ->select('zone_id', 'note')
            ->get()
            ->groupBy('zone_id');

        // ── Build the user vector once (reused for every zone comparison) ─────
        $userVector = $this->buildUserVector($profile);

        return $zones
            ->map(function ($zone) use (
                $profile, $preferredActivities, $preferredStyles,
                $inferredTerrain, $zoneFeedbacks, $userVector,
            ) {
                // ── Content-based: cosine similarity ─────────────────────────
                $zoneVector = $this->buildZoneVector(
                    $zone, $preferredActivities, $preferredStyles, $inferredTerrain
                );
                $cosine = $this->cosineSimilarity($userVector, $zoneVector);

                // ── Collaborative signals (normalized 0-1) ────────────────────
                $feedbacksForZone = $zoneFeedbacks->get($zone->id, collect());
                $positiveCount    = $feedbacksForZone->where('note', '>=', 4)->count();
                $avgNote          = $feedbacksForZone->avg('note') ?? 0.0;

                $collab1 = $positiveCount >= 2 ? 1.0 : 0.0;   // ≥2 positive reviews from similar users
                $collab2 = $avgNote >= 4.5 ? 1.0 : 0.0;        // avg note ≥ 4.5 from similar users
                $collaborativeNorm = ($collab1 + $collab2) / 2.0;

                // ── Final blended score ───────────────────────────────────────
                $finalScore = ($cosine * 0.7) + ($collaborativeNorm * 0.3);

                $zone                  = clone $zone;
                $zone->score           = $finalScore;
                $zone->score_breakdown = [
                    'cosine_similarity'   => round($cosine, 6),
                    'collaborative_bonus' => round($collaborativeNorm, 6),
                    'final_score'         => round($finalScore, 6),
                    'user_vector'         => array_map(fn ($v) => round($v, 6), $userVector),
                    'zone_vector'         => array_map(fn ($v) => round($v, 6), $zoneVector),
                ];

                return $zone;
            })
            ->sortByDesc('score')
            ->take(5)
            ->values();
    }

    public function scoreGear(ProfileCampeur $profile, Collection $gear): Collection
    {
        $userGearVector = $this->buildUserGearVector($profile);

        // Pre-compute max tarif for price normalization
        $maxTarif = $gear->max('tarif_nuit') ?: 1.0;

        return $gear
            ->map(function ($item) use ($userGearVector, $maxTarif, $profile) {
                $gearVector = $this->buildGearVector($item, $maxTarif, $profile);
                $score      = $this->cosineSimilarity($userGearVector, $gearVector);

                $item        = clone $item;
                $item->score = $score;

                return $item;
            })
            ->sortByDesc('score')
            ->take(10)
            ->values();
    }

    /**
     * Score partner centres for a camper.
     *
     * Centres lack the rating/terrain signals zones have, so this is a
     * transparent weighted blend rather than a cosine:
     *   score = budget_fit*0.45 + equipment_richness*0.35 + capacity_fit*0.20
     *
     * Accepts the array shape produced by CentreLookupService::findPartnerCentres
     * and returns the same arrays enriched with `score` + `score_breakdown`.
     */
    public function scoreCentres(ProfileCampeur $profile, Collection $centres): Collection
    {
        $budget     = $profile->budget_range ?? 'moderate';
        $idealPrice = match ($budget) {
            'budget'  => 40.0,
            'premium' => 120.0,
            default   => 75.0,
        };
        $group = (int) ($profile->getAttribute('inferred_group_size') ?? 2);

        return $centres
            ->map(function ($c) use ($idealPrice, $group) {
                $price     = (float) ($c['price_per_night'] ?? 0);
                $budgetFit = $price > 0
                    ? max(0.0, 1.0 - min(abs($price - $idealPrice) / $idealPrice, 1.0))
                    : 0.4;

                $equipList     = $c['equipment_list'] ?? [];
                $equip         = is_array($equipList) ? count($equipList) : 0;
                $equipRichness = min($equip / 8.0, 1.0);

                $cap    = $c['capacite'] ?? null;
                $capFit = $cap === null ? 0.5 : ((int) $cap >= max($group, 2) ? 1.0 : 0.5);

                $score = ($budgetFit * 0.45) + ($equipRichness * 0.35) + ($capFit * 0.20);

                $c['score']           = round($score, 4);
                $c['score_breakdown'] = [
                    'budget_fit'         => round($budgetFit, 3),
                    'equipment_richness' => round($equipRichness, 3),
                    'capacity_fit'       => round($capFit, 3),
                ];

                return $c;
            })
            ->sortByDesc('score')
            ->take(6)
            ->values();
    }

    /**
     * Score upcoming events for a camper.
     *   score = difficulty_fit*0.30 + budget_fit*0.25 + tag_match*0.45
     * tag_match reuses the terrain synonym resolver so French event tags
     * ("montagne") align with the camper's preferred styles. Expects stdClass /
     * array rows (status=scheduled, remaining_spots>0 filtered by the caller).
     */
    public function scoreEvents(ProfileCampeur $profile, Collection $events): Collection
    {
        $skill  = $profile->skill_level ?? 'beginner';
        $budget = $profile->budget_range ?? 'moderate';
        $styles = array_merge(
            $this->toArray($profile->preferred_trip_styles),
            $this->toArray($profile->preferred_activities),
        );

        $skillTier = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3, 'expert' => 4][$skill] ?? 1;
        $idealMax  = match ($budget) { 'budget' => 30.0, 'premium' => 200.0, default => 70.0 };
        $diffTiers = ['easy' => 1, 'moderate' => 2, 'difficult' => 3, 'expert' => 4];

        return $events
            ->map(function ($e) use ($skillTier, $idealMax, $styles, $diffTiers) {
                $e = (array) $e;

                // Difficulty fit (camping/no-difficulty events are neutral).
                $diff = $e['difficulty'] ?? null;
                if ($diff === null || ! isset($diffTiers[$diff])) {
                    $difficultyFit = 0.6;
                } else {
                    $gap           = abs($skillTier - $diffTiers[$diff]);
                    $difficultyFit = max(0.0, 1.0 - $gap * 0.34);
                }

                // Budget fit (free events score full).
                $price     = (float) ($e['price'] ?? 0);
                $budgetFit = $price <= 0 ? 1.0
                    : ($price <= $idealMax ? 1.0 : max(0.0, 1.0 - ($price - $idealMax) / $idealMax));

                // Tag match via terrain keys + substring (French↔French and FR↔EN).
                $tags    = $this->toArray($e['tags'] ?? []);
                $matched = 0;
                foreach ($tags as $tag) {
                    $t    = strtolower(trim((string) $tag));
                    $tKey = $this->terrainKey($t);
                    foreach ($styles as $style) {
                        $s = strtolower(trim((string) $style));
                        if ($s === '') {
                            continue;
                        }
                        if (($tKey !== null && $this->terrainKey($s) === $tKey) ||
                            str_contains($t, $s) || str_contains($s, $t)) {
                            $matched++;
                            break;
                        }
                    }
                }
                $tagMatch = count($tags) > 0 ? min($matched / count($tags), 1.0) : 0.0;

                $score = ($difficultyFit * 0.30) + ($budgetFit * 0.25) + ($tagMatch * 0.45);

                $e['score']           = round($score, 4);
                $e['score_breakdown'] = [
                    'difficulty_fit' => round($difficultyFit, 3),
                    'budget_fit'     => round($budgetFit, 3),
                    'tag_match'      => round($tagMatch, 3),
                ];

                return $e;
            })
            ->sortByDesc('score')
            ->take(6)
            ->values();
    }

    // ── Vector builders ───────────────────────────────────────────────────────

    /**
     * Build the 6-dimensional user vector for zone scoring.
     * All dimensions are 0.0-1.0.
     * Safe to call with a purely static ProfileCampeur (behavioral attrs absent = 0/null defaults).
     */
    public function buildUserVector(ProfileCampeur $profile): array
    {
        // [0] skill encoded
        $skill = self::SKILL_ENC[$profile->skill_level ?? ''] ?? 0.0;

        // [1] budget encoded
        $budget = self::BUDGET_ENC[$profile->budget_range ?? ''] ?? 0.5;

        // [2] terrain match weight
        // Read behavioral attribute if present (attached by TripPlannerService::buildEffectiveProfile).
        $inferredTerrain = $profile->getAttribute('inferred_terrain_preference');
        $staticStyles    = $this->toArray($profile->preferred_trip_styles);
        $terrainWeight   = match (true) {
            $inferredTerrain !== null => 1.0,
            ! empty($staticStyles)   => 0.5,
            default                  => 0.0,
        };

        // [3] activity richness
        $activities      = $this->toArray($profile->preferred_activities);
        $activityRichness = min(count($activities) / 10.0, 1.0);

        // [4] experience weight
        $trips = (int) ($profile->total_trips ?? 0);
        if ($trips > 0) {
            $experienceWeight = min($trips / 20.0, 1.0);
        } else {
            $groupSize        = $profile->getAttribute('inferred_group_size');
            $experienceWeight = $groupSize !== null ? min((int) $groupSize / 20.0, 1.0) : 0.0;
        }

        // [5] confidence weight
        $confidence = (float) ($profile->getAttribute('behavioral_confidence') ?? 0.0);

        return [$skill, $budget, $terrainWeight, $activityRichness, $experienceWeight, $confidence];
    }

    /**
     * Build the 6-dimensional zone vector for a specific user context.
     * Zone dimension 2 (terrain match) is user-relative — the same zone can
     * score differently for different users.
     */
    public function buildZoneVector(
        object  $zone,
        array   $userActivities,
        array   $userStyles,
        ?string $inferredTerrain,
    ): array {
        // [0] difficulty encoded
        $diffEncoded = self::DIFF_ENC[$zone->difficulty ?? 'easy'] ?? 0.0;

        // [1] rating value
        $ratingValue = min((float) ($zone->rating ?? 0.0) / 5.0, 1.0);

        // [2] terrain match (user-relative)
        $zoneTerrain  = $zone->terrain_type ?? '';
        $terrainMatch = $this->computeTerrainMatch($zoneTerrain, $inferredTerrain, $userStyles);

        // [3] activity overlap (Jaccard similarity)
        $zoneActivities  = $this->toArray($zone->activities ?? []);
        $activityOverlap = $this->jaccardSimilarity($userActivities, $zoneActivities);

        // [4] accessibility score: beginner-friendliness weighted by ease of access
        $isBeginnerFriendly = (bool) ($zone->is_beginner_friendly ?? false);
        $accessibility      = ($isBeginnerFriendly ? 1.0 : 0.5) * (1.0 - $diffEncoded);

        // [5] social proof: high rating × review volume
        $reviewsCount = (int) ($zone->reviews_count ?? 0);
        $socialProof  = $ratingValue * min($reviewsCount / 50.0, 1.0);

        return [$diffEncoded, $ratingValue, $terrainMatch, $activityOverlap, $accessibility, $socialProof];
    }

    /**
     * Build the 4-dimensional user vector for gear scoring.
     * Shares dimensions 0-3 with the zone user vector at the same indices.
     */
    public function buildUserGearVector(ProfileCampeur $profile): array
    {
        $fullVector = $this->buildUserVector($profile);

        // [0] terrain_affinity  = zone user dim 2
        // [1] budget_encoded    = zone user dim 1
        // [2] skill_encoded     = zone user dim 0
        // [3] experience_weight = zone user dim 4
        return [$fullVector[2], $fullVector[1], $fullVector[0], $fullVector[4]];
    }

    /**
     * Build the 4-dimensional gear item vector.
     * `$maxTarif` is the collection-wide maximum price for normalization.
     */
    public function buildGearVector(object $gear, float $maxTarif, ProfileCampeur $profile): array
    {
        $inferredTerrain = $profile->getAttribute('inferred_terrain_preference');
        $preferredStyles = $this->toArray($profile->preferred_trip_styles);

        // [0] terrain match: gear trip_type_tags vs user terrain preference
        $tags = $this->toArray($gear->trip_type_tags ?? []);
        $gearTerrainMatch = $this->computeTagTerrainMatch($tags, $inferredTerrain, $preferredStyles);

        // [1] price normalized (0 = cheapest, 1 = most expensive in collection)
        $tarif          = (float) ($gear->tarif_nuit ?? 0.0);
        $priceNorm      = $maxTarif > 0 ? min($tarif / $maxTarif, 1.0) : 0.0;

        // [2] condition encoded
        $condition      = $gear->condition ?? '';
        $conditionScore = self::COND_ENC[$condition] ?? 0.25;

        // [3] availability: proxy via is_rentable (quantite_dispo > 0 guaranteed by query filter)
        $availability   = ($gear->is_rentable ?? false) ? 1.0 : 0.5;

        return [$gearTerrainMatch, $priceNorm, $conditionScore, $availability];
    }

    // ── Core math ─────────────────────────────────────────────────────────────

    /**
     * Standard cosine similarity. Returns 0.0 for zero-magnitude vectors.
     * Result is clamped to [0.0, 1.0] to absorb floating-point rounding.
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dot  = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        $n    = max(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $va   = $a[$i] ?? 0.0;
            $vb   = $b[$i] ?? 0.0;
            $dot  += $va * $vb;
            $magA += $va * $va;
            $magB += $vb * $vb;
        }

        $mag = sqrt($magA) * sqrt($magB);

        return $mag > 1e-10 ? max(0.0, min(1.0, $dot / $mag)) : 0.0;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Compute terrain match score between a zone terrain type and user context.
     *   1.0 – exact match with the behavioral inferred terrain
     *   0.5 – zone terrain found in static preferred styles (partial signal)
     *   0.0 – no match
     */
    private function computeTerrainMatch(string $zoneTerrain, ?string $inferredTerrain, array $staticStyles): float
    {
        if ($zoneTerrain === '') {
            return 0.0;
        }

        $zt = strtolower($zoneTerrain);

        // Strongest signal: behavioral inferred terrain matches exactly.
        if ($inferredTerrain !== null && $zt === strtolower($inferredTerrain)) {
            return 1.0;
        }

        // Static preferred styles, matched via French/English synonyms.
        $synonyms = self::TERRAIN_SYNONYMS[$zt] ?? [$zt];
        foreach ($staticStyles as $style) {
            $s = strtolower(trim((string) $style));
            if ($s === '') {
                continue;
            }
            foreach ($synonyms as $syn) {
                if ($s === $syn || str_contains($s, $syn) || str_contains($syn, $s)) {
                    return 0.8;
                }
            }
        }

        return 0.0;
    }

    /**
     * Resolve a free-text term (French or English) to its English terrain key.
     * Lets us compare French gear tags ("montagne") with English behavioral
     * terrain ("mountain") — they both resolve to the "mountain" key.
     */
    private function terrainKey(string $term): ?string
    {
        $t = strtolower(trim($term));
        if ($t === '') {
            return null;
        }
        foreach (self::TERRAIN_SYNONYMS as $key => $syns) {
            foreach ($syns as $syn) {
                if ($t === $syn || str_contains($t, $syn) || str_contains($syn, $t)) {
                    return $key;
                }
            }
        }
        return null;
    }

    /**
     * Compute terrain match from gear trip_type_tags vs user terrain context.
     * Language-proof: a French tag ("montagne") matches an English behavioral
     * terrain ("mountain") and vice-versa, via shared terrain keys.
     *   1.0 – a tag matches the inferred terrain
     *   0.5 – a tag matches any static preferred style
     *   0.0 – no match
     */
    private function computeTagTerrainMatch(array $tags, ?string $inferredTerrain, array $styles): float
    {
        if (empty($tags)) {
            return 0.0;
        }

        $infKey = $inferredTerrain !== null ? $this->terrainKey($inferredTerrain) : null;

        foreach ($tags as $tag) {
            $tagStr = strtolower((string) $tag);
            if ($inferredTerrain !== null && (
                ($infKey !== null && $this->terrainKey($tagStr) === $infKey) ||
                str_contains($tagStr, strtolower($inferredTerrain)) ||
                str_contains(strtolower($inferredTerrain), $tagStr)
            )) {
                return 1.0;
            }
        }

        foreach ($tags as $tag) {
            $tagStr = strtolower((string) $tag);
            $tagKey = $this->terrainKey($tagStr);
            foreach ($styles as $style) {
                $s = strtolower(trim((string) $style));
                if ($s === '') {
                    continue;
                }
                if (($tagKey !== null && $this->terrainKey($s) === $tagKey) ||
                    str_contains($tagStr, $s) || str_contains($s, $tagStr)) {
                    return 0.5;
                }
            }
        }

        return 0.0;
    }

    /**
     * Jaccard similarity between two string sets (case-insensitive, substring aware).
     * intersection / union
     */
    private function jaccardSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $aLower = array_map('strtolower', $a);
        $bLower = array_map('strtolower', $b);

        $intersection = 0;
        $matched      = [];

        foreach ($aLower as $itemA) {
            foreach ($bLower as $key => $itemB) {
                if (isset($matched[$key])) {
                    continue;
                }
                if (str_contains($itemB, $itemA) || str_contains($itemA, $itemB)) {
                    $intersection++;
                    $matched[$key] = true;
                    break;
                }
            }
        }

        $union = count($aLower) + count($bLower) - $intersection;

        return $union > 0 ? round($intersection / $union, 6) : 0.0;
    }

    /**
     * Normalise a value that may be a JSON string, an array, or null.
     */
    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
