<?php

namespace App\Services\Payments;

use App\Models\AdminWalletTransaction;
use App\Models\Balance;
use App\Models\PaymentTransaction;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CommissionService;
use Illuminate\Support\Facades\DB;

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
        string $gateway,           // clictopay | bank_transfer | reservation_credit | cash
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

    /**
     * Confirm a submitted payment (bank transfer OR ClicToPay) for events /
     * centres / materielles. Extracted from AdminPaymentReviewController::confirm()
     * so the admin-review path (manual bank-transfer verification, $confirmedBy =
     * the admin's user id) and the ClicToPayController automatic
     * getOrderStatusExtended.do confirmation ($confirmedBy = null, system-confirmed)
     * share the exact same money/status logic — no separate code path to drift.
     *
     * Locked so a concurrent duplicate call (double admin click, or a camper
     * hitting a ClicToPay returnUrl twice) can't double-credit.
     */
    public static function confirmSubmittedPayment(string $type, int $id, ?int $confirmedBy): array
    {
        return DB::transaction(function () use ($type, $id, $confirmedBy) {
            $reservation = self::findReservation($type, $id, lock: true);
            if (!$reservation) {
                return ['error' => 404, 'message' => 'Réservation introuvable.'];
            }

            if (!in_array($reservation->status, ['paiement_soumis', 'solde_soumis'])) {
                return ['error' => 422, 'message' => 'Aucun paiement en attente de validation pour cette réservation.'];
            }

            $wasBalance = ($reservation->status === 'solde_soumis');
            $isCash = ($reservation->payment_method === 'cash');
            $newStatus = self::resolveConfirmedStatus($reservation, $type);

            // Amount actually received with THIS confirmation (before mutating fields)
            $grossTotal = (float) ($reservation->total_price ?? $reservation->montant_total ?? (($reservation->amount_now ?? 0) + ($reservation->amount_later ?? 0)));
            $tranche = $wasBalance
                ? (float) ($reservation->amount_later ?? 0)
                : ($reservation->payment_option === 'deposit' ? (float) ($reservation->amount_now ?? $grossTotal) : $grossTotal);

            $reservation->status = $newStatus;
            $reservation->payment_confirmed_at = now();
            $reservation->confirmed_by = $confirmedBy;
            if ($wasBalance) {
                $reservation->amount_later = 0; // balance settled in full
            }
            $reservation->save();

            // ── Money traceability (Task A-04) ──────────────────────────────────
            $reservationTypeKey = match ($type) { 'events' => 'event', 'centres' => 'centre', default => 'materielle' };
            $gatewayLabel = $isCash ? 'cash' : ($reservation->clictopay_order_id ? 'clictopay' : 'bank_transfer');
            self::recordGatewayPayment(
                (int) $reservation->user_id, (int) $reservation->id, $reservationTypeKey,
                $tranche, $gatewayLabel,
                ($reservation->payment_reference ?? 'RES-' . $reservation->id) . ($wasBalance ? '-SOLDE' : '')
            );

            if (($isCash || $wasBalance) && $tranche > 0) {
                [$providerId, $commissionKey, $refType] = match ($type) {
                    'events'  => [(int) $reservation->group_id, 'group', 'event_reservation'],
                    'centres' => [(int) $reservation->centre_id, 'center', 'centre_reservation'],
                    default   => [(int) $reservation->fournisseur_id,
                                  (User::find($reservation->fournisseur_id)?->role_id === 4 ? 'supplier' : 'camper'),
                                  'materiel_reservation'],
                };

                if ($isCash) {
                    // Cash never touched the platform — book what the provider now owes
                    // us (their commission + the camper's fee) instead of crediting them.
                    self::recordCashCollection(
                        $providerId, $commissionKey, $grossTotal, (float) ($reservation->platform_fee_amount ?? 0)
                    );
                } else {
                    // Balance settlement: the provider already accepted the reservation, so
                    // the remaining tranche is earned NOW — credit it (fee was charged on tranche 1).
                    self::creditManualTranche(
                        $refType, (int) $reservation->id, $providerId, $commissionKey, (int) $reservation->user_id,
                        $grossTotal, (float) ($reservation->platform_fee_amount ?? 0), $tranche, false,
                        "Solde réservation #{$reservation->id} (paiement manuel)"
                    );
                }
            }

            $notifTitle = $isCash ? 'Paiement en espèces confirmé ✓' : ($wasBalance ? 'Solde confirmé ✓' : 'Paiement confirmé ✓');
            $notifBody = $isCash
                ? "Votre paiement en espèces ({$reservation->payment_reference}) a été confirmé. Votre réservation est entièrement payée."
                : ($wasBalance
                    ? "Votre solde restant ({$reservation->payment_reference}) a été validé. Votre réservation est entièrement payée."
                    : "Votre paiement ({$reservation->payment_reference}) a été validé. Votre réservation est en attente de confirmation par le prestataire.");

            return [
                'success' => true,
                'newStatus' => $newStatus,
                'userId' => (int) $reservation->user_id,
                'notifTitle' => $notifTitle,
                'notifBody' => $notifBody,
            ];
        });
    }

    /**
     * Reject a submitted payment. Extracted from AdminPaymentReviewController::reject()
     * for the same reason as confirmSubmittedPayment() — shared by the admin-review
     * path and ClicToPayController's automatic orderStatus != 2 handling.
     */
    public static function rejectSubmittedPayment(string $type, int $id): array
    {
        return DB::transaction(function () use ($type, $id) {
            $reservation = self::findReservation($type, $id, lock: true);
            if (!$reservation) {
                return ['error' => 404, 'message' => 'Réservation introuvable.'];
            }

            if (!in_array($reservation->status, ['paiement_soumis', 'solde_soumis'])) {
                return ['error' => 422, 'message' => 'Aucun paiement en attente de validation pour cette réservation.'];
            }

            // A rejected balance reverts to the host-accepted state (balance still owed) so
            // the camper can resubmit; a rejected initial payment becomes paiement_invalide
            // (the provider/admin can call markCashReceived() again from that status for cash).
            $wasBalance = ($reservation->status === 'solde_soumis');
            $isCash = ($reservation->payment_method === 'cash');
            $reservation->status = $wasBalance
                ? match ($type) {
                    'events' => 'confirmée', 'centres' => 'approved', default => 'confirmed'
                }
            : 'paiement_invalide';
            $reservation->save();

            $notifTitle = $isCash ? 'Paiement en espèces non confirmé' : ($wasBalance ? 'Solde non validé' : 'Virement non trouvé');
            $notifBody = $isCash
                ? "Nous n'avons pas pu confirmer votre paiement en espèces ({$reservation->payment_reference}). Merci de vérifier avec le prestataire et de le signaler à nouveau."
                : ($wasBalance
                    ? "Nous n'avons pas pu vérifier votre virement de solde ({$reservation->payment_reference}). Veuillez vérifier et soumettre à nouveau."
                    : "Nous n'avons pas pu vérifier votre virement ({$reservation->payment_reference}). Veuillez vérifier la référence et soumettre à nouveau.");

            return [
                'success' => true,
                'newStatus' => $reservation->status,
                'userId' => (int) $reservation->user_id,
                'notifTitle' => $notifTitle,
                'notifBody' => $notifBody,
            ];
        });
    }

    /**
     * Determine the next status after a submitted payment is confirmed (admin or
     * automatic gateway). See AdminPaymentReviewController::resolveConfirmedStatus()
     * original docblock for the full reasoning — mirrored verbatim here.
     */
    private static function resolveConfirmedStatus(mixed $r, string $type): string
    {
        if ($r->status === 'solde_soumis' || $r->payment_method === 'cash') {
            return match ($type) {
                'events' => 'confirmée',
                'centres' => 'approved',
                default => 'confirmed', // materielles
            };
        }

        // Initial payment confirmed → host review queue.
        return match ($type) {
            'events' => 'en_attente_validation',
            'centres' => 'pending',
            default => 'pending', // materielles
        };
    }

    private static function findReservation(string $type, int $id, bool $lock = false): mixed
    {
        return match ($type) {
            'events' => $lock ? Reservations_events::whereKey($id)->lockForUpdate()->first() : Reservations_events::find($id),
            'centres' => $lock ? Reservations_centre::whereKey($id)->lockForUpdate()->first() : Reservations_centre::find($id),
            'materielles' => $lock ? Reservations_materielles::whereKey($id)->lockForUpdate()->first() : Reservations_materielles::find($id),
            default => null,
        };
    }

    /**
     * Record a CASH-AT-CENTRE payment: the provider collected the full amount
     * directly from the camper, so — unlike creditManualTranche() — we never
     * credit their solde_disponible (they already physically hold the money).
     * Instead we book what they now owe the platform (their commission + the
     * camper's platform fee, neither of which ever passed through us) onto
     * Balance::solde_du_plateforme, settled later via a withdrawal offset or
     * an admin marking it collected (see AdminPaymentController).
     *
     * Deliberately does NOT log anything to admin_wallet_transactions here —
     * the platform hasn't actually received this money yet, only a debt claim
     * against the provider. Logging 'commission'/'platform_fee' at this point
     * would count uncollected cash as recognized revenue. That entry is made
     * at actual settlement time instead (Balance::debiterDette() call sites in
     * AdminPaymentController — manual settleDebt() or the withdrawal offset).
     *
     * Cash is always paid in full on arrival — no deposit/balance split — so
     * this is always a single, whole-amount call, not tranche-based.
     *
     * Idempotency relies on the caller's own status guard (confirm() only runs
     * this from status paiement_soumis/solde_soumis, and immediately advances
     * the reservation past it) — same protection creditManualTranche's "was
     * this already processed" case implicitly relies on for its first tranche.
     */
    public static function recordCashCollection(
        int $providerId,
        string $commissionKey,
        float $grossFull,
        float $platformFee,
    ): void {
        if ($grossFull <= 0 || $providerId <= 0) {
            return;
        }

        $baseFull = max(0, round($grossFull - $platformFee, 2));
        $calc     = CommissionService::calculateForUser($commissionKey, $baseFull, $providerId);
        $commission = round($calc['commission'], 2);
        $fee        = round($platformFee, 2);
        $owed       = round($commission + $fee, 2);

        if ($owed > 0) {
            Balance::forUser($providerId)->crediterDette($owed);
        }
    }
}
