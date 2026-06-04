<?php

namespace App\Services\AI;

use App\Services\AI\Behavioral\BehavioralProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Derives a behaviorally-inferred user profile from real platform activity:
 * bookings, gear rentals, zone reviews, and favorites.
 *
 * This replaces the static ProfileCampeur fields (which users set once and
 * forget) with signals that reflect what a user actually did on the platform.
 *
 * --- Signal 1: inferred_skill_level ---
 * Source: reservations_centres.group_skill_level
 * The spec describes joining via camping_zones.centre_id, but that column is
 * unpopulated in the current dataset. The group_skill_level field on the
 * booking captures the same information (the group's declared skill level at
 * booking time) and is actually populated. Mapped to numeric values, averaged,
 * then bucketed as beginner / intermediate / advanced.
 *
 * --- Signal 2: inferred_budget_range ---
 * Source: total_price on centre reservations + montant_total on gear rentals.
 * Average spend per booking across all completed reservations.
 *
 * --- Signal 3: inferred_terrain_preference ---
 * Source: feedbacks joined to camping_zones + zone favorites.
 * Net score per terrain type (positive ratings + favorites minus negative ratings).
 * Min 2 signals required.
 *
 * --- Signal 4: inferred_gear_needed ---
 * Source: completed gear reservations vs centre-only trips.
 *
 * --- Signal 5: inferred_group_size ---
 * Source: average nbr_place across completed centre bookings.
 *
 * --- Signal 6: confidence_score ---
 * Based on total booking count, with bonuses for feedback and favorites data.
 */
class BehavioralProfileService
{
    private const CACHE_TTL    = 3600;
    private const KEY_PREFIX   = 'behavioral_profile:';

    private const SKILL_MAP = [
        'beginner'     => 1.0,
        'intermediate' => 2.0,
        'advanced'     => 3.0,
        'expert'       => 3.0,
        'mixed'        => 1.5,
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    public function compute(int $userId): BehavioralProfile
    {
        $cacheKey = self::KEY_PREFIX . $userId;

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $this->fromArray($cached, fromCache: true);
        }

        try {
            $profile = $this->computeFromDb($userId);
        } catch (\Throwable $e) {
            Log::error('behavioral_profile_compute_failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            $profile = $this->emptyProfile($userId);
        }

        Cache::put($cacheKey, $profile->toArray(), self::CACHE_TTL);

        Log::info('behavioral_profile_computed', [
            'user_id'          => $userId,
            'confidence_score' => $profile->confidence_score,
            'skill'            => $profile->inferred_skill_level,
            'budget'           => $profile->inferred_budget_range,
            'terrain'          => $profile->inferred_terrain_preference,
        ]);

        return $profile;
    }

    public function invalidate(int $userId): void
    {
        $cacheKey = self::KEY_PREFIX . $userId;
        try {
            Cache::forget($cacheKey);

            Log::info('behavioral_profile_invalidated', [
                'user_id'   => $userId,
                'cache_key' => $cacheKey,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('behavioral_profile_invalidate_failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ── Core computation ──────────────────────────────────────────────────────

    private function computeFromDb(int $userId): BehavioralProfile
    {
        // ── Completed centre bookings (the primary activity signal) ───────────
        $centreBookings = DB::table('reservations_centres')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->select('nbr_place', 'total_price', 'group_skill_level')
            ->get();

        $bookingCount = $centreBookings->count();

        // ── Completed gear rentals ────────────────────────────────────────────
        $gearRentals = DB::table('reservations_materielles')
            ->where('user_id', $userId)
            ->whereIn('status', ['confirmed', 'returned'])
            ->select('montant_total')
            ->get();

        // ── Feedbacks (approved, on zones) ────────────────────────────────────
        $zoneFeedbacks = DB::table('feedbacks as f')
            ->join('camping_zones as z', 'z.id', '=', 'f.zone_id')
            ->where('f.user_id', $userId)
            ->where('f.status', 'approved')
            ->whereNotNull('z.terrain_type')
            ->select('z.terrain_type', 'f.note')
            ->get();

        // ── Zone favorites ────────────────────────────────────────────────────
        $zoneFavorites = DB::table('favorites as fav')
            ->join('camping_zones as z', 'z.id', '=', 'fav.favoritable_id')
            ->where('fav.user_id', $userId)
            ->where('fav.favoritable_type', 'zone')
            ->whereNotNull('z.terrain_type')
            ->select('z.terrain_type')
            ->get();

        // ── Compute each signal ───────────────────────────────────────────────
        $skillLevel   = $this->computeSkillLevel($centreBookings);
        $budgetRange  = $this->computeBudgetRange($centreBookings, $gearRentals);
        $terrain      = $this->computeTerrainPreference($zoneFeedbacks, $zoneFavorites);
        $gearNeeded   = $this->computeGearNeeded($gearRentals->count(), $bookingCount);
        $groupSize    = $this->computeGroupSize($centreBookings);
        $confidence   = $this->computeConfidence($bookingCount, $zoneFeedbacks->isNotEmpty(), $zoneFavorites->isNotEmpty());

        return new BehavioralProfile(
            userId:                       $userId,
            inferred_skill_level:         $skillLevel,
            inferred_budget_range:        $budgetRange,
            inferred_terrain_preference:  $terrain,
            inferred_gear_needed:         $gearNeeded,
            inferred_group_size:          $groupSize,
            confidence_score:             $confidence,
            has_sufficient_data:          $confidence >= 0.4,
            computed_at:                  now()->toIso8601String(),
        );
    }

    // ── Signal implementations ────────────────────────────────────────────────

    /**
     * Signal 1 — skill level.
     *
     * Uses group_skill_level from centre bookings (the field that captures the
     * group's actual skill at booking time). Maps to numeric values, averages,
     * then buckets with the spec thresholds (0.0-1.4 → beginner, etc.).
     */
    private function computeSkillLevel(\Illuminate\Support\Collection $bookings): ?string
    {
        $values = $bookings
            ->pluck('group_skill_level')
            ->filter(fn ($v) => isset(self::SKILL_MAP[$v]))
            ->map(fn ($v) => self::SKILL_MAP[$v]);

        if ($values->isEmpty()) {
            return null;
        }

        $avg = $values->average();

        return match (true) {
            $avg <= 1.4 => 'beginner',
            $avg <= 2.4 => 'intermediate',
            default     => 'advanced',
        };
    }

    /**
     * Signal 2 — budget range.
     *
     * Merges all spend amounts (centre total_price + gear montant_total),
     * computes the average, and buckets.
     */
    private function computeBudgetRange(
        \Illuminate\Support\Collection $centreBookings,
        \Illuminate\Support\Collection $gearRentals,
    ): ?string {
        $amounts = $centreBookings
            ->pluck('total_price')
            ->filter(fn ($v) => $v !== null && (float) $v > 0)
            ->map(fn ($v) => (float) $v)
            ->concat(
                $gearRentals
                    ->pluck('montant_total')
                    ->filter(fn ($v) => $v !== null && (float) $v > 0)
                    ->map(fn ($v) => (float) $v)
            );

        if ($amounts->isEmpty()) {
            return null;
        }

        $avg = $amounts->average();

        return match (true) {
            $avg < 60    => 'budget',
            $avg <= 150  => 'moderate',
            default      => 'premium',
        };
    }

    /**
     * Signal 3 — terrain preference.
     *
     * Net score per terrain: +1 per high feedback (note>=4), -1 per low feedback
     * (note<=2), +1 per favorite. Returns the terrain with the highest net score.
     * Requires at least 2 total signals (feedbacks + favorites) to return a value.
     */
    private function computeTerrainPreference(
        \Illuminate\Support\Collection $feedbacks,
        \Illuminate\Support\Collection $favorites,
    ): ?string {
        if ($feedbacks->isEmpty() && $favorites->isEmpty()) {
            return null;
        }

        $totalSignals = $feedbacks->count() + $favorites->count();
        if ($totalSignals < 2) {
            return null;
        }

        $scores = [];

        foreach ($feedbacks as $fb) {
            $terrain = $fb->terrain_type;
            if (! isset($scores[$terrain])) {
                $scores[$terrain] = 0;
            }
            if ($fb->note >= 4) {
                $scores[$terrain]++;
            } elseif ($fb->note <= 2) {
                $scores[$terrain]--;
            }
        }

        foreach ($favorites as $fav) {
            $terrain = $fav->terrain_type;
            if (! isset($scores[$terrain])) {
                $scores[$terrain] = 0;
            }
            $scores[$terrain]++;
        }

        if (empty($scores)) {
            return null;
        }

        // Return the terrain with the highest net score.
        arsort($scores);
        $best      = array_key_first($scores);
        $bestScore = $scores[$best];

        return $bestScore > 0 ? $best : null;
    }

    /**
     * Signal 4 — gear needed.
     *
     * If the user has completed gear rentals → true (they use gear from the platform).
     * If they have >= 3 centre bookings with zero gear rentals → false (bring own gear).
     * Otherwise → null (insufficient data).
     */
    private function computeGearNeeded(int $gearCount, int $centreCount): ?bool
    {
        if ($gearCount >= 1) {
            return true;
        }
        if ($centreCount >= 3 && $gearCount === 0) {
            return false;
        }
        return null;
    }

    /**
     * Signal 5 — group size.
     *
     * Average nbr_place across completed centre bookings, rounded to the
     * nearest integer.
     */
    private function computeGroupSize(\Illuminate\Support\Collection $bookings): ?int
    {
        $values = $bookings
            ->pluck('nbr_place')
            ->filter(fn ($v) => $v !== null && (int) $v > 0);

        if ($values->isEmpty()) {
            return null;
        }

        return (int) round($values->average());
    }

    /**
     * Signal 6 — confidence score.
     *
     * Based on booking count (primary signal volume), with bonuses for
     * feedback and favorites data.
     */
    private function computeConfidence(int $bookingCount, bool $hasFeedbacks, bool $hasFavorites): float
    {
        $base = match (true) {
            $bookingCount === 0 => 0.0,
            $bookingCount <= 2  => 0.3,
            $bookingCount <= 5  => 0.6,
            $bookingCount <= 10 => 0.8,
            default             => 1.0,
        };

        return min(1.0, $base + ($hasFeedbacks ? 0.1 : 0.0) + ($hasFavorites ? 0.1 : 0.0));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function emptyProfile(int $userId): BehavioralProfile
    {
        return new BehavioralProfile(
            userId:                       $userId,
            inferred_skill_level:         null,
            inferred_budget_range:        null,
            inferred_terrain_preference:  null,
            inferred_gear_needed:         null,
            inferred_group_size:          null,
            confidence_score:             0.0,
            has_sufficient_data:          false,
            computed_at:                  now()->toIso8601String(),
        );
    }

    private function fromArray(array $data, bool $fromCache): BehavioralProfile
    {
        return new BehavioralProfile(
            userId:                       $data['user_id'],
            inferred_skill_level:         $data['inferred_skill_level'] ?? null,
            inferred_budget_range:        $data['inferred_budget_range'] ?? null,
            inferred_terrain_preference:  $data['inferred_terrain_preference'] ?? null,
            inferred_gear_needed:         isset($data['inferred_gear_needed']) ? (bool) $data['inferred_gear_needed'] : null,
            inferred_group_size:          isset($data['inferred_group_size']) ? (int) $data['inferred_group_size'] : null,
            confidence_score:             (float) ($data['confidence_score'] ?? 0.0),
            has_sufficient_data:          (bool) ($data['has_sufficient_data'] ?? false),
            computed_at:                  $data['computed_at'] ?? now()->toIso8601String(),
            from_cache:                   $fromCache,
        );
    }
}
