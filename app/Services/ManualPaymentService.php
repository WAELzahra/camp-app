<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\ProviderPaymentPreference;

/**
 * Encapsulates manual (Flouci bank-transfer) payment business logic.
 *
 * - Wallet payments continue to use the existing wallet/escrow flow.
 * - Manual payments skip wallet checks entirely; the admin confirms the
 *   bank transfer out-of-band before the reservation is fulfilled.
 */
class ManualPaymentService
{
    /** True when the admin has enabled the manual payment gateway. */
    public static function isEnabled(): bool
    {
        return (bool) PlatformSetting::get('manual_payment_enabled', false);
    }

    /** External Flouci link the camper should use for the bank transfer. */
    public static function flouciLink(): string
    {
        return (string) PlatformSetting::get('payment_link_flouci', '');
    }

    /**
     * Compute payment split for a booking.
     *
     * Returns:
     *   payment_option  — 'full' | 'deposit'
     *   amount_now      — amount the camper must pay now
     *   amount_later    — balance still owed (0 for full)
     *   deposit_pct     — percentage used (null for full)
     */
    public static function computeAmounts(int $providerId, float $total): array
    {
        $minTotal  = (int) PlatformSetting::get('deposit_min_total', 150);
        $minPct    = (int) PlatformSetting::get('deposit_min_percentage', 20);
        $maxPct    = (int) PlatformSetting::get('deposit_max_percentage', 80);

        $pref = ProviderPaymentPreference::forUser($providerId);

        $canDeposit = $pref->accepts_deposits && $total >= $minTotal;

        if (!$canDeposit) {
            return [
                'payment_option' => 'full',
                'amount_now'     => round($total, 2),
                'amount_later'   => 0.0,
                'deposit_pct'    => null,
            ];
        }

        $pct     = max($minPct, min($maxPct, $pref->deposit_percentage));
        $amtNow  = round($total * $pct / 100, 2);
        $amtLater= round($total - $amtNow, 2);

        return [
            'payment_option' => 'deposit',
            'amount_now'     => $amtNow,
            'amount_later'   => $amtLater,
            'deposit_pct'    => $pct,
        ];
    }

    /**
     * Validate that a payment_option chosen by the camper is actually allowed
     * for this booking. Returns error string or null if valid.
     */
    public static function validateOption(string $option, int $providerId, float $total): ?string
    {
        if (!in_array($option, ['full', 'deposit'])) {
            return 'Invalid payment option.';
        }
        if ($option === 'deposit') {
            $minTotal = (int) PlatformSetting::get('deposit_min_total', 150);
            $pref     = ProviderPaymentPreference::forUser($providerId);
            if (!$pref->accepts_deposits) {
                return 'This provider does not accept deposit payments.';
            }
            if ($total < $minTotal) {
                return "Deposits are only allowed for bookings over {$minTotal} TND.";
            }
        }
        return null;
    }
}
