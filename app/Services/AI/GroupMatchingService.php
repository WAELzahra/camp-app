<?php

namespace App\Services\AI;

use App\Models\ProfileCampeur;
use App\Models\User;
use App\Services\AI\Adapters\LLMAdapterInterface;
use App\Services\AI\Matching\ClusterResult;
use App\Services\AI\Matching\DBSCANClusterer;
use App\Services\AI\Matching\GroupMatch;
use App\Services\AI\Matching\KMeansClusterer;
use App\Services\AI\Matching\VectorBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GroupMatchingService
{
    public function __construct(
        private readonly LLMAdapterInterface $llm,
        private readonly KMeansClusterer     $kMeans,
        private readonly DBSCANClusterer     $dbscan,
        private readonly VectorBuilder       $vectorBuilder,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    public function clusterAllProfiles(): array
    {
        $profiles = ProfileCampeur::with('profile:id,user_id')->get();

        if ($profiles->isEmpty()) {
            return [
                'kmeans'         => ['assignments' => [], 'centroids' => [], 'iterations' => 0, 'converged' => true],
                'dbscan'         => [],
                'total_profiles' => 0,
                'clusters'       => [],
            ];
        }

        $vectors       = $this->vectorBuilder->buildVectors($profiles);
        $vectorsForAlgo = array_map(fn ($v) => ['id' => $v->profileId, 'dims' => $v->dimensions], $vectors);

        $kmeansResult = $this->kMeans->cluster($vectorsForAlgo);
        $dbscanResult = $this->dbscan->cluster($vectorsForAlgo);

        Cache::put('group_matching:kmeans_assignments', $kmeansResult, 3600);
        Cache::put('group_matching:dbscan_assignments', $dbscanResult, 3600);
        Cache::put('group_matching:vectors', $vectors, 3600);
        Cache::put('group_matching:clustered_at', now()->toISOString(), 3600);

        Log::info('group_matching_clustered', [
            'total_profiles' => count($profiles),
            'k'              => 4,
            'iterations'     => $kmeansResult['iterations'],
            'converged'      => $kmeansResult['converged'],
        ]);

        $clusterResults = $this->buildClusterResults($kmeansResult, $vectors);

        return [
            'kmeans'         => $kmeansResult,
            'dbscan'         => $dbscanResult,
            'total_profiles' => count($profiles),
            'clusters'       => $clusterResults,
        ];
    }

    public function findMatchingGroups(ProfileCampeur $profile, int $limit = 5): array
    {
        try {
            return $this->doFindMatchingGroups($profile, $limit);
        } catch (\Throwable $e) {
            Log::error('group_matching_failed', [
                'profile_id' => $profile->profile_id ?? $profile->id,
                'error'      => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getClusterStats(): array
    {
        try {
            $kmeansResult = Cache::get('group_matching:kmeans_assignments');
            if (! $kmeansResult) {
                $this->clusterAllProfiles();
                $kmeansResult = Cache::get('group_matching:kmeans_assignments');
            }

            $vectors        = Cache::get('group_matching:vectors', []);
            $dbscanResult   = Cache::get('group_matching:dbscan_assignments', []);
            $clusteredAt    = Cache::get('group_matching:clustered_at', 'never');
            $clusterResults = $this->buildClusterResults($kmeansResult ?? ['assignments' => [], 'centroids' => []], $vectors);

            $noiseCount = count(array_filter($dbscanResult, fn ($r) => $r['cluster'] === -1));

            return [
                'clusters'           => array_map(fn ($c) => $c->toArray(), $clusterResults),
                'total'              => count($vectors),
                'clustered_at'       => $clusteredAt,
                'algorithm'          => 'kmeans-4-euclidean',
                'dbscan_noise_count' => $noiseCount,
            ];
        } catch (\Throwable $e) {
            Log::error('cluster_stats_failed', ['error' => $e->getMessage()]);
            return [
                'clusters'           => [],
                'total'              => 0,
                'clustered_at'       => 'never',
                'algorithm'          => 'kmeans-4-euclidean',
                'dbscan_noise_count' => 0,
            ];
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function doFindMatchingGroups(ProfileCampeur $profile, int $limit): array
    {
        // Step 1 — Ensure cluster assignments are cached
        $cached = Cache::get('group_matching:kmeans_assignments');
        if (! $cached) {
            $this->clusterAllProfiles();
            $cached = Cache::get('group_matching:kmeans_assignments');
        }

        // Step 2 — Build vector for querying user
        $vectors  = Cache::get('group_matching:vectors', []);
        $maxTrips = count($vectors) > 0
            ? max(array_map(fn ($v) => $v->raw['total_trips'] ?? 0, $vectors))
            : 10;
        $userVector = $this->vectorBuilder->buildSingleVector($profile, (float) max($maxTrips, 1));

        // Step 3 — Determine user's cluster via nearest centroid
        $centroids      = $cached['centroids'] ?? [];
        $userClusterId  = 0;
        $minDist        = PHP_FLOAT_MAX;
        foreach ($centroids as $cIdx => $centroid) {
            $dist = $this->euclideanDistance($userVector->dimensions, $centroid);
            if ($dist < $minDist) {
                $minDist       = $dist;
                $userClusterId = $cIdx;
            }
        }

        // Step 4 — Check DBSCAN for outlier status
        $dbscanResult = Cache::get('group_matching:dbscan_assignments', []);
        $isOutlier    = false;
        $profileId    = $profile->profile_id ?? $profile->id;
        foreach ($dbscanResult as $entry) {
            if ($entry['id'] === $profileId && $entry['cluster'] === -1) {
                $isOutlier = true;
                break;
            }
        }

        // Build lookup: profile_id → cluster_id from K-Means
        $profileClusterMap = [];
        foreach ($cached['assignments'] ?? [] as $a) {
            $profileClusterMap[$a['id']] = $a['cluster_id'];
        }

        // Step 5 — Fetch group users from DB (role is on User, not Profile).
        // The group role is named "organizer" in this schema (legacy aliases:
        // "groupe"/"group"); query all of them so real groups are matched.
        $groupeUsers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['organizer', 'groupe', 'group']))
            ->with(['profile.profileCampeur'])
            ->get()
            ->filter(fn ($u) => $u->profile?->profileCampeur !== null);

        // Fallback: if no group users have a ProfileCampeur, match against campeur users
        if ($groupeUsers->isEmpty()) {
            $groupeUsers = User::whereHas('role', fn ($q) => $q->where('name', 'campeur'))
                ->with(['profile.profileCampeur'])
                ->get()
                ->filter(fn ($u) => $u->profile?->profileCampeur !== null);
        }

        $candidates = [];

        foreach ($groupeUsers as $groupeUser) {
            $groupeProfile = $groupeUser->profile?->profileCampeur ?? null;
            if ($groupeProfile === null) {
                continue;
            }

            $gProfileId = $groupeProfile->profile_id ?? $groupeProfile->id;

            // Skip self
            if ($gProfileId === $profileId) {
                continue;
            }

            $groupeVector = $this->vectorBuilder->buildSingleVector($groupeProfile, (float) max($maxTrips, 1));
            $similarity   = $this->cosineSimilarity($userVector->dimensions, $groupeVector->dimensions);

            $groupeClusterId = $profileClusterMap[$gProfileId] ?? -1;

            // Filter by cluster (same cluster, or adjacent if outlier)
            if (! $isOutlier && $groupeClusterId !== $userClusterId) {
                continue;
            }

            $sharedTraits = $this->computeSharedTraits($profile, $groupeProfile);

            $candidates[] = [
                'user'         => $groupeUser,
                'profile'      => $groupeProfile,
                'similarity'   => $similarity,
                'clusterId'    => $groupeClusterId,
                'sharedTraits' => $sharedTraits,
            ];
        }

        // Step 6 — Sort by similarity descending, take top $limit
        usort($candidates, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);
        $topCandidates = array_slice($candidates, 0, $limit);

        // Step 7 — LLM enrichment for top 3 if similarity > 0.7
        $matches = [];
        foreach ($topCandidates as $idx => $c) {
            $llmEnriched    = false;
            $whyExplanation = $this->buildRuleBasedExplanation($c['sharedTraits']);

            $useProvider = config('ai.provider') !== 'mock';
            $featureOn   = config('ai.features.group_matching', true);

            if ($featureOn && $useProvider && $idx < 3 && $c['similarity'] > 0.7) {
                $enriched = $this->enrichWithLLM($profile, $c['profile'], $c['similarity'], $c['sharedTraits']);
                if ($enriched !== null) {
                    $whyExplanation = $enriched;
                    $llmEnriched    = true;
                }
            }

            $memberProfiles = $this->buildMemberProfileSummary($c['profile']);

            // Real display name: groupe accounts store their org name across
            // first_name/last_name (e.g. "Club Aventure Tunis"); fall back to a
            // generic label only when both are empty.
            $displayName = trim(($c['user']->first_name ?? '') . ' ' . ($c['user']->last_name ?? ''));
            if ($displayName === '') {
                $displayName = "Groupe #{$c['user']->id}";
            }

            $matches[] = new GroupMatch(
                groupId:          $c['user']->id,
                groupName:        $displayName,
                clusterId:        $c['clusterId'],
                similarityScore:  round($c['similarity'], 4),
                compatibilityPct: round($c['similarity'] * 100, 1),
                sharedTraits:     $c['sharedTraits'],
                whyExplanation:   $whyExplanation,
                llmEnriched:      $llmEnriched,
                memberProfiles:   $memberProfiles,
            );
        }

        Log::info('group_matching_query', [
            'profile_id'     => $profileId,
            'skill_level'    => $profile->skill_level,
            'cluster_id'     => $userClusterId,
            'is_outlier'     => $isOutlier,
            'matches_found'  => count($matches),
            'top_similarity' => $matches[0]->similarityScore ?? 0,
            'llm_enriched'   => collect($matches)->contains('llmEnriched', true),
        ]);

        return $matches;
    }

    private function computeSharedTraits(ProfileCampeur $a, ProfileCampeur $b): array
    {
        $traits = [];

        if ($a->skill_level && $a->skill_level === $b->skill_level) {
            $traits[] = "Même niveau d'expérience";
        }
        if ($a->budget_range && $a->budget_range === $b->budget_range) {
            $traits[] = 'Budget similaire';
        }
        if ($a->comfort_level && $a->comfort_level === $b->comfort_level) {
            $traits[] = 'Préférences de confort identiques';
        }

        $stylesA = $this->decodeJson($a->preferred_trip_styles);
        $stylesB = $this->decodeJson($b->preferred_trip_styles);
        if (! empty(array_intersect($stylesA, $stylesB))) {
            $traits[] = 'Styles de voyage communs';
        }

        $tripsA = (int) ($a->total_trips ?? 0);
        $tripsB = (int) ($b->total_trips ?? 0);
        if ($tripsA > 5 && $tripsB > 5) {
            $traits[] = 'Campeurs expérimentés';
        }

        return $traits;
    }

    private function buildRuleBasedExplanation(array $sharedTraits): string
    {
        if (empty($sharedTraits)) {
            return 'Ce groupe pourrait compléter votre profil avec de nouvelles perspectives.';
        }
        return implode(' et ', $sharedTraits) . '. Ce groupe semble très compatible avec votre profil.';
    }

    private function buildMemberProfileSummary(ProfileCampeur $groupeProfile): array
    {
        return [
            [
                'skill'   => $groupeProfile->skill_level   ?? 'beginner',
                'budget'  => $groupeProfile->budget_range  ?? 'budget',
                'comfort' => $groupeProfile->comfort_level ?? 'basic',
                'trips'   => $groupeProfile->total_trips   ?? 0,
            ],
        ];
    }

    private function enrichWithLLM(
        ProfileCampeur $userProfile,
        ProfileCampeur $groupeProfile,
        float $similarity,
        array $sharedTraits,
    ): ?string {
        try {
            $systemPrompt = 'You are a group matching assistant for TunisiaCamp. '
                . 'Given two camper profiles and their similarity score, write ONE sentence in French '
                . 'explaining why they would make good camping companions. Be specific and friendly. '
                . 'Respond ONLY with the sentence, no JSON, no markdown.';

            $userMsg = json_encode([
                'profile_a'     => [
                    'skill'   => $userProfile->skill_level,
                    'budget'  => $userProfile->budget_range,
                    'comfort' => $userProfile->comfort_level,
                ],
                'profile_b'     => [
                    'skill'   => $groupeProfile->skill_level,
                    'budget'  => $groupeProfile->budget_range,
                    'comfort' => $groupeProfile->comfort_level,
                ],
                'similarity'    => round($similarity, 3),
                'shared_traits' => $sharedTraits,
            ], JSON_UNESCAPED_UNICODE);

            $raw = $this->llm->complete($systemPrompt, $userMsg, 200);
            $trimmed = trim($raw);

            return $trimmed !== '' ? $trimmed : null;
        } catch (\Throwable $e) {
            Log::warning('group_matching_llm_failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildClusterResults(array $kmeansResult, array $vectors): array
    {
        $centroids   = $kmeansResult['centroids'] ?? [];
        $assignments = $kmeansResult['assignments'] ?? [];

        if (empty($centroids)) {
            return [];
        }

        // Map profile_id → vector dimensions for cohesion calculation
        $vectorMap = [];
        foreach ($vectors as $v) {
            $vectorMap[$v->profileId] = $v->dimensions;
        }

        // Group assignments by cluster
        $clusterMembers = [];
        foreach ($assignments as $a) {
            $clusterMembers[$a['cluster_id']][] = $a['id'];
        }

        $results = [];
        foreach ($centroids as $cIdx => $centroid) {
            $members  = $clusterMembers[$cIdx] ?? [];
            $cohesion = $this->calculateCohesion($members, $vectorMap);

            $results[] = new ClusterResult(
                clusterId:    $cIdx,
                centroid:     $centroid,
                memberIds:    $members,
                memberCount:  count($members),
                clusterLabel: $this->vectorBuilder->deriveClusterLabel($centroid),
                cohesion:     $cohesion,
            );
        }

        return $results;
    }

    private function calculateCohesion(array $memberIds, array $vectorMap): float
    {
        if (count($memberIds) < 2) {
            return 0.0;
        }

        $totalDist = 0.0;
        $pairs     = 0;
        $count     = count($memberIds);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $dimA = $vectorMap[$memberIds[$i]] ?? null;
                $dimB = $vectorMap[$memberIds[$j]] ?? null;
                if ($dimA !== null && $dimB !== null) {
                    $totalDist += $this->euclideanDistance($dimA, $dimB);
                    $pairs++;
                }
            }
        }

        return $pairs > 0 ? round($totalDist / $pairs, 4) : 0.0;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot  = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }
        if ($magA == 0.0 || $magB == 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($magA) * sqrt($magB));
    }

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $diff = ($a[$i] ?? 0.0) - ($b[$i] ?? 0.0);
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    private function decodeJson(mixed $value): array
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
