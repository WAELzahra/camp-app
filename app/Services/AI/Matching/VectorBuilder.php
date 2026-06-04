<?php

namespace App\Services\AI\Matching;

use App\Models\ProfileCampeur;
use Illuminate\Support\Collection;

class VectorBuilder
{
    private const SKILL_MAP   = ['beginner' => 0, 'intermediate' => 1, 'advanced' => 2, 'expert' => 3];
    private const COMFORT_MAP = ['basic' => 0, 'standard' => 1, 'glamping' => 2];
    private const BUDGET_MAP  = ['budget' => 0, 'moderate' => 1, 'premium' => 2];

    /**
     * Build normalized ProfileVector DTOs for a collection of ProfileCampeur models.
     * Normalizes total_trips using the max value within the collection.
     *
     * @param  Collection $profiles
     * @return ProfileVector[]
     */
    public function buildVectors(Collection $profiles): array
    {
        $maxTrips = $profiles->max('total_trips') ?: 1;
        $vectors  = [];

        foreach ($profiles as $profile) {
            $vectors[] = $this->buildSingleVector($profile, (float) $maxTrips);
        }

        return $vectors;
    }

    public function buildSingleVector(ProfileCampeur $profile, float $maxTrips = 10.0): ProfileVector
    {
        $skillRaw   = self::SKILL_MAP[$profile->skill_level   ?? ''] ?? 0;
        $comfortRaw = self::COMFORT_MAP[$profile->comfort_level ?? ''] ?? 0;
        $budgetRaw  = self::BUDGET_MAP[$profile->budget_range  ?? ''] ?? 0;
        $tripsRaw   = (int) ($profile->total_trips ?? 0);

        $styles     = $this->decodeJson($profile->preferred_trip_styles);
        $activities = $this->decodeJson($profile->preferred_activities);

        $dimensions = [
            $skillRaw / 3.0,
            $comfortRaw / 2.0,
            $budgetRaw / 2.0,
            $maxTrips > 0 ? min($tripsRaw / $maxTrips, 1.0) : 0.0,
            min(count($styles), 5) / 5.0,
            min(count($activities), 10) / 10.0,
        ];

        $userId = $profile->profile?->user_id ?? 0;

        return new ProfileVector(
            profileId:    $profile->profile_id ?? $profile->id,
            userId:       $userId,
            dimensions:   $dimensions,
            raw: [
                'skill_level'           => $profile->skill_level   ?? 'beginner',
                'comfort_level'         => $profile->comfort_level ?? 'basic',
                'budget_range'          => $profile->budget_range  ?? 'budget',
                'total_trips'           => $tripsRaw,
                'preferred_trip_styles' => $styles,
                'preferred_activities'  => $activities,
            ],
            skillLevel:   $profile->skill_level   ?? 'beginner',
            budgetRange:  $profile->budget_range  ?? 'budget',
            comfortLevel: $profile->comfort_level ?? 'basic',
        );
    }

    public function deriveClusterLabel(array $centroid): string
    {
        $skill   = $centroid[0] ?? 0.0;
        $comfort = $centroid[1] ?? 0.0;
        $budget  = $centroid[2] ?? 0.0;
        $acts    = $centroid[5] ?? 0.0;

        if ($skill > 0.6 && $comfort < 0.3) {
            return 'Campeurs Aventuriers';
        }
        if ($comfort > 0.6 && $skill < 0.5) {
            return 'Campeurs Glamping';
        }
        if ($budget > 0.6) {
            return 'Campeurs Premium';
        }
        if ($budget < 0.3 && $acts > 0.5) {
            return 'Campeurs Budget Actifs';
        }
        return 'Campeurs Polyvalents';
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
