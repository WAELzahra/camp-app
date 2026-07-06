<?php

namespace App\Services\Payments;

use App\Models\AdminWalletTransaction;
use App\Models\Balance;
use App\Models\PaymentTransaction;
use App\Models\WalletTransaction;
use App\Services\CommissionService;

/**
 * Money movement for MANUAL (gateway) payments + unified payment tracing.
 *
 * The wallet flow credits providers inside each confirm endpoint; manual
 * payments historically only flipped a status — no provider credit, no
 * commission, no history. This service closes that gap:
 *
 *  - recordGatewayPayment(): one payment_transactions row per confirmed
 *    payment (manual + wallet) → feeds the admin "Transactions" tab.
 *  - creditManualTranche(): provider balance credit + wallet history +
 *    admin wallet commission/platform fee, mirroring the wallet branch math.
 *    Deposit flows settle in two tranches (amount_now, then amount_later).
 */
class ReservationLedgerService
{
    /** Trace a confirmed payment. Idempotent per (reservation, type, amount). */
    public static function recordGatewayPayment(
        int $userId,
        int $reservationId,
        string $reservationType,   // centre | event | materielle
        float $amount,
        string $gateway,           // flouci | clictopay | bank_transfer | reservation_credit
        ?string $reference = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        // Idempotency includes the reference: a deposit's two tranches carry
        // distinct references (…-SOLDE suffix), so a 50/50 split still records
        // both while genuine retries stay deduplicated.
        $exists = PaymentTransaction::where('reservation_id', $reservationId)
            ->where('reservation_type', $reservationType)
            ->where('payment_type', 'reservation')
            ->where('amount', round($amount, 2))
            ->where('gateway_reference', $reference)
            ->exists();
        if ($exists) {
            return;
        }

        PaymentTransaction::create([
            'reservation_id'    => $reservationId,
            'reservation_type'  => $reservationType,
            'user_id'           => $userId,
            'gateway'           => $gateway,
            'amount'            => round($amount, 2),
            'status'            => 'completed',
            'gateway_reference' => $reference,
            'payment_type'      => 'reservation',
            'processed_at'      => now(),
        ]);
    }

    /**
     * Credit the provider for one settled tranche of a MANUAL reservation.
     *
     * @param string $refType       centre_reservation | event_reservation | materiel_reservation
     * @param string $commissionKey CommissionService key: center | group | supplier | camper
     * @param float  $grossFull     full gross paid by the camper (fee included)
     * @param float  $platformFee   platform fee (charged once, on the first tranche)
     * @param float  $tranche       amount being settled now (amount_now or amount_later)
     * @param bool   $isFirstTranche true at provider acceptance, false at balance settlement
     */
    public static function creditManualTranche(
        string $refType,
        int $reservationId,
        int $providerId,
        string $commissionKey,
        int $payerId,
        float $grossFull,
        float $platformFee,
        float $tranche,
        bool $isFirstTranche,
        string $description,
    ): void {
        if ($tranche <= 0 || $providerId <= 0) {
            return;
        }

        // Idempotency: first tranche only if no income row yet; the balance
        // tranche only if exactly one row exists (retries / double clicks).
        $incomeRows = WalletTransaction::where('user_id', $providerId)
            ->where('type', 'credit')
            ->where('category', 'reservation_income')
            ->where('reference_type', $refType)
            ->where('reference_id', $reservationId)
            ->count();
        if (($isFirstTranche && $incomeRows > 0) || (!$isFirstTranche && $incomeRows !== 1)) {
            return;
        }

        // Commission is computed on the FULL base (gross - fee) then prorated
        // per tranche, so a mid-contract rate change never alters totals.
        $baseFull = max(0, round($grossFull - $platformFee, 2));
        $calc     = CommissionService::calculateForUser($commissionKey, $baseFull, $providerId);
        $ratio    = $grossFull > 0 ? min(1, $tranche / $grossFull) : 0;

        $commissionTranche = round($calc['commission'] * $ratio, 2);
        if (!$isFirstTranche) {
            // remainder on the last tranche — absorbs any rounding drift
            $alreadyCharged    = (float) WalletTransaction::where('user_id', $providerId)
                ->where('category', 'reservation_income')
                ->where('reference_type', $refType)
                ->where('reference_id', $reservationId)
                ->sum('commission_amount');
            $commissionTranche = max(0, round($calc['commission'] - $alreadyCharged, 2));
        }

        $feeTranche = $isFirstTranche ? round($platformFee, 2) : 0.0;
        $net        = max(0, round($tranche - $feeTranche - $commissionTranche, 2));

        Balance::forUser($providerId)->crediter($net);
        WalletTransaction::logCredit(
            $providerId, 'reservation_income',
            round($tranche, 2),
            round($calc['rate'] * 100, 2),
            $commissionTranche,
            $net,
            $refType, $reservationId,
            $description,
            $payerId
        );

        if ($feeTranche > 0) {
            AdminWalletTransaction::log(
                'platform_fee', $feeTranche,
                $refType, $reservationId,
                "Platform fee (paiement manuel) — {$refType} #{$reservationId}",
                $payerId
            );
        }
        if ($commissionTranche > 0) {
            AdminWalletTransaction::log(
                'commission', $commissionTranche,
                $refType, $reservationId,
                "Commission (paiement manuel) — {$refType} #{$reservationId}",
                $providerId
            );
        }
    }
}
