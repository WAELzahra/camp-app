<?php

namespace App\Services\AI\Behavioral;

use App\Models\ProfileCampeur;

/**
 * Readonly DTO representing a user's behaviorally inferred preferences.
 * All signals are derived from actual platform activity (bookings, reviews,
 * favorites) rather than manually entered profile fields.
 */
final class BehavioralProfile
{
    public function __construct(
        public readonly int     $userId,
        public readonly ?string $inferred_skill_level,         // beginner|intermediate|advanced|null
        public readonly ?string $inferred_budget_range,        // budget|moderate|premium|null
        public readonly ?string $inferred_terrain_preference,  // forest|mountain|desert|coastal|plain|wetland|null
        public readonly ?bool   $inferred_gear_needed,         // true|false|null
        public readonly ?int    $inferred_group_size,          // null if no history
        public readonly float   $confidence_score,             // 0.0–1.0
        public readonly bool    $has_sufficient_data,          // true when confidence >= 0.4
        public readonly string  $computed_at,
        public readonly bool    $from_cache = false,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id'                      => $this->userId,
            'inferred_skill_level'         => $this->inferred_skill_level,
            'inferred_budget_range'        => $this->inferred_budget_range,
            'inferred_terrain_preference'  => $this->inferred_terrain_preference,
            'inferred_gear_needed'         => $this->inferred_gear_needed,
            'inferred_group_size'          => $this->inferred_group_size,
            'confidence_score'             => $this->confidence_score,
            'has_sufficient_data'          => $this->has_sufficient_data,
            'computed_at'                  => $this->computed_at,
            'from_cache'                   => $this->from_cache,
        ];
    }

    /**
     * Returns a merged array that looks like a ProfileCampeur attribute set.
     * Behavioral values win over static when:
     *   - behavioral confidence >= 0.4 (has_sufficient_data = true), AND
     *   - the behavioral value is not null.
     * Falls back to static profile values for any field where behavioral is null.
     */
    public function mergeWithStatic(ProfileCampeur $static): array
    {
        $useBehavioral = $this->has_sufficient_data;

        // Normalise JSON-encoded arrays the model may return as strings.
        $toArr = static function (mixed $v): array {
            if (is_array($v)) {
                return $v;
            }
            if (is_string($v) && $v !== '') {
                $decoded = json_decode($v, true);
                return is_array($decoded) ? $decoded : [];
            }
            return [];
        };

        // Terrain preference → preferred_trip_styles (prepend behavioral terrain).
        $behavioralStyles = ($useBehavioral && $this->inferred_terrain_preference !== null)
            ? [$this->inferred_terrain_preference]
            : [];
        $staticStyles = $toArr($static->preferred_trip_styles);
        $mergedStyles = $behavioralStyles
            ? array_values(array_unique(array_merge($behavioralStyles, $staticStyles)))
            : $staticStyles;

        return [
            'profile_id'           => $static->profile_id,
            'skill_level'          => ($useBehavioral && $this->inferred_skill_level !== null)
                ? $this->inferred_skill_level
                : ($static->skill_level ?? 'beginner'),
            'budget_range'         => ($useBehavioral && $this->inferred_budget_range !== null)
                ? $this->inferred_budget_range
                : ($static->budget_range ?? 'moderate'),
            'comfort_level'        => $static->comfort_level ?? 'standard',
            'preferred_trip_styles' => $mergedStyles,
            'preferred_activities' => $toArr($static->preferred_activities),
            'gear_preferences'     => $toArr($static->gear_preferences),
            'total_trips'          => (int) ($static->total_trips ?? 0),
            // Behavioral extras for downstream use
            'inferred_group_size'  => $this->inferred_group_size,
            'inferred_gear_needed' => $this->inferred_gear_needed,
            'behavioral_confidence' => $this->confidence_score,
        ];
    }
}
