<?php

namespace App\Services;

/**
 * Generates stable, human-readable payment references.
 *
 *  - Reservation:     TC-{YYYY}-{zero-padded id, 6 digits}   e.g. TC-2026-000042
 *  - Wallet recharge: WALLET-{userId}-{unix timestamp}        e.g. WALLET-17-1718387400
 */
class PaymentReferenceService
{
    public static function forReservation(int $id, ?int $year = null): string
    {
        return sprintf('TC-%d-%06d', $year ?? date('Y'), $id);
    }

    public static function forWalletRecharge(int $userId): string
    {
        return sprintf('WALLET-%d-%d', $userId, time());
    }
}
