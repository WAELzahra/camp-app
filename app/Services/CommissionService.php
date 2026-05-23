<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\CustomCommissionRule;

class CommissionService
{
    private const DEFAULTS = [
        'commission_group'    => 5,
        'commission_center'   => 8,
        'commission_supplier' => 10,
        'commission_camper'   => 2,
        'commission_guide'    => 7,
        'service_fee_camper'  => 3,
    ];

    public static function rate(string $type): float
    {
        $key = match ($type) {
            'center'   => 'commission_center',
            'supplier' => 'commission_supplier',
            'camper'   => 'commission_camper',
            'guide'    => 'commission_guide',
            default    => 'commission_group',
        };

        return (float) PlatformSetting::get($key, self::DEFAULTS[$key]) / 100;
    }

    public static function serviceFeeRate(): float
    {
        return (float) PlatformSetting::get('service_fee_camper', self::DEFAULTS['service_fee_camper']) / 100;
    }

    /**
     * Calculate commission and net revenue for a given receiver type and amount.
     *
     * Returns: ['rate', 'commission', 'net_revenue']
     */
    public static function calculate(string $type, float $amount): array
    {
        $rate       = self::rate($type);
        $commission = round($amount * $rate, 2);
        $netRevenue = round($amount - $commission, 2);

        return [
            'rate'        => $rate,
            'commission'  => $commission,
            'net_revenue' => $netRevenue,
        ];
    }

    /**
     * Look up whether a user has an active custom commission rule.
     * Returns the rule's commission_rate (as a 0–1 decimal) or null if no override.
     */
    public static function customRateForUser(int $userId): ?float
    {
        $rule = CustomCommissionRule::where('is_active', true)
            ->whereHas('users', fn($q) => $q->where('user_id', $userId))
            ->first();

        return $rule ? $rule->commission_rate / 100 : null;
    }

    /**
     * Calculate commission for a specific user, applying their custom rule if one exists.
     * Falls back to the global rate for $type if no override is found.
     *
     * Returns: ['rate', 'commission', 'net_revenue', 'is_custom_rate']
     */
    public static function calculateForUser(string $type, float $amount, int $userId): array
    {
        $customRate = self::customRateForUser($userId);
        $rate       = $customRate ?? self::rate($type);
        $commission = round($amount * $rate, 2);
        $netRevenue = round($amount - $commission, 2);

        return [
            'rate'            => $rate,
            'commission'      => $commission,
            'net_revenue'     => $netRevenue,
            'is_custom_rate'  => $customRate !== null,
        ];
    }

    /**
     * Calculate service fee charged on top of the base amount (paid by camper).
     *
     * Returns: ['fee_rate', 'fee_amount', 'total']
     */
    public static function addServiceFee(float $amount): array
    {
        $feeRate = self::serviceFeeRate();
        $fee     = round($amount * $feeRate, 2);

        return [
            'fee_rate'   => $feeRate,
            'fee_amount' => $fee,
            'total'      => round($amount + $fee, 2),
        ];
    }
}
