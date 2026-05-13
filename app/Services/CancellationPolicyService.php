<?php

namespace App\Services;

use App\Models\CancellationPolicy;
use Carbon\Carbon;

class CancellationPolicyService
{
    /**
     * Resolve the applicable policy for a given reservation type.
     * For 'centre' type, checks for a custom per-centre policy first.
     */
    public static function getPolicy(string $type, ?int $centreId = null): ?CancellationPolicy
    {
        if ($type === 'centre' && $centreId) {
            $custom = CancellationPolicy::where('type', 'centre')
                ->where('centre_id', $centreId)
                ->where('is_active', true)
                ->first();
            if ($custom) return $custom;
        }

        return CancellationPolicy::where('type', $type)
            ->whereNull('centre_id')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Calculate the cancellation fee.
     *
     * Grace period: if the reservation was created within grace_period_hours of now, full refund.
     * Tier selection: among tiers where hours_remaining >= tier.hours_before,
     *   pick the highest hours_before (most lenient matching tier).
     * If no tier matches (past start date), 100% fee applies.
     *
     * @param  Carbon|null  $reservationCreatedAt  When the reservation was created (for grace period check).
     */
    public static function calculateFee(
        CancellationPolicy $policy,
        Carbon $startDate,
        float $totalPaid,
        ?Carbon $reservationCreatedAt = null
    ): array {
        // ── Grace period check ────────────────────────────────────────────────
        if ($policy->grace_period_hours !== null && $reservationCreatedAt !== null) {
            $hoursSinceCreation = (int) $reservationCreatedAt->diffInHours(now());
            if ($hoursSinceCreation <= $policy->grace_period_hours) {
                return [
                    'policy_name'          => $policy->name,
                    'fee_percentage'       => 0.0,
                    'fee_amount'           => 0.0,
                    'refund_amount'        => $totalPaid,
                    'hours_remaining'      => max(0, (int) now()->diffInHours($startDate, false)),
                    'tier_label'           => "Grace period — full refund (booked {$hoursSinceCreation}h ago)",
                    'grace_period_applied' => true,
                    'grace_period_hours'   => $policy->grace_period_hours,
                ];
            }
        }

        // ── Normal tier logic ─────────────────────────────────────────────────
        $hoursRemaining = max(0, (int) now()->diffInHours($startDate, false));

        $applicableTier = $policy->tiers()
            ->where('hours_before', '<=', $hoursRemaining)
            ->orderByDesc('hours_before')
            ->first();

        if ($applicableTier) {
            $feePercent = (float) $applicableTier->fee_percentage;
            $label      = $applicableTier->label ?? ($feePercent === 0.0
                ? 'Free cancellation'
                : number_format($feePercent, 0) . '% cancellation fee');
        } else {
            $feePercent = 100.0;
            $label      = 'No refund (past start date)';
        }

        $feeAmount    = round($totalPaid * $feePercent / 100, 2);
        $refundAmount = max(0, round($totalPaid - $feeAmount, 2));

        return [
            'policy_name'          => $policy->name,
            'fee_percentage'       => $feePercent,
            'fee_amount'           => $feeAmount,
            'refund_amount'        => $refundAmount,
            'hours_remaining'      => $hoursRemaining,
            'tier_label'           => $label,
            'grace_period_applied' => false,
            'grace_period_hours'   => $policy->grace_period_hours,
        ];
    }

    /**
     * Convenience wrapper: resolve policy + calculate fee in one call.
     * Returns null if no policy is configured (caller should treat as full refund).
     */
    public static function preview(
        string $type,
        Carbon $startDate,
        float $totalPaid,
        ?int $centreId = null,
        ?Carbon $reservationCreatedAt = null
    ): ?array {
        $policy = self::getPolicy($type, $centreId);
        if (!$policy) return null;

        return self::calculateFee($policy, $startDate, $totalPaid, $reservationCreatedAt);
    }
}
