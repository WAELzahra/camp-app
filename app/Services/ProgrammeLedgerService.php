<?php

namespace App\Services;

use App\Models\AdminWalletTransaction;
use App\Models\Balance;
use App\Models\ProgrammeReservation;
use App\Models\ProgrammeReservationShare;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Generalizes the EventReservationMaterial escrow → confirm → payout → reverse
 * pattern (see ReservationEventController::updateStatus/cancelByUser/cancelReservation)
 * to an arbitrary number of already-existing platform actors per reservation
 * (event organizer, centre owner, equipment supplier) instead of just suppliers.
 */
class ProgrammeLedgerService
{
    /**
     * Split a reservation's escrowed amount across the items the camper
     * actually kept (see ProgrammeReservation::selectedItems — they may have
     * deselected some), proportional to each item's price and the actual
     * amount collected (so shares always sum to what the camper paid, even
     * after a promo code discount).
     */
    public function createShares(ProgrammeReservation $reservation): void
    {
        $items = $reservation->selectedItems;

        $rawBase = (float) $items->sum('price') * $reservation->participants_count;
        // Uses total_price (not amount_now) so a deposit booking still computes
        // shares off the FULL final amount — payout is deferred until the whole
        // amount (deposit + balance) has actually been collected, see payoutShares().
        $ratio = $rawBase > 0 ? $reservation->total_price / $rawBase : 0;

        foreach ($items as $item) {
            $gross = round($item->price * $reservation->participants_count * $ratio, 2);
            if ($gross <= 0) {
                continue;
            }

            $ownerUserId = $item->ownerUserId();
            if (!$ownerUserId) {
                continue; // referenced listing was deleted or has no resolvable owner — skip rather than crash
            }

            $rate = $this->resolveCommissionRate($item, $ownerUserId);
            $commission = round($gross * $rate, 2);
            $net = round($gross - $commission, 2);

            ProgrammeReservationShare::create([
                'programme_reservation_id' => $reservation->id,
                'programme_item_id' => $item->id,
                'owner_user_id' => $ownerUserId,
                'gross_amount' => $gross,
                'commission_rate' => round($rate * 100, 2),
                'commission_amount' => $commission,
                'net_amount' => $net,
                'credited' => false,
            ]);
        }
    }

    /**
     * Admin-triggered confirmation (V1 has no per-actor accept workflow):
     * releases the camper's escrow (wallet payments only — a manual/bank-transfer
     * booking never locked wallet funds in the first place) and credits every
     * item's real owner their net share. Only call this once the reservation
     * is fully paid (amount_later == 0) — see confirmReservation()'s guard.
     */
    public function payoutShares(ProgrammeReservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            if ($reservation->payment_method === 'wallet') {
                Balance::forUser($reservation->user_id)->releaseEscrow($reservation->amount_now);
            }

            $shares = $reservation->shares()->where('credited', false)->with('item')->get();

            foreach ($shares as $share) {
                Balance::forUser($share->owner_user_id)->crediter($share->net_amount);
                WalletTransaction::logCredit(
                    $share->owner_user_id,
                    'reservation_income',
                    $share->gross_amount,
                    $share->commission_rate,
                    $share->commission_amount,
                    $share->net_amount,
                    'programme_reservation_share',
                    $share->id,
                    "Revenu programme — {$share->item?->displayTitle()}",
                    $reservation->user_id
                );

                if ($share->commission_amount > 0) {
                    AdminWalletTransaction::log(
                        'commission',
                        $share->commission_amount,
                        'programme_reservation_share',
                        $share->id,
                        "Commission programme — {$share->item?->displayTitle()}",
                        $share->owner_user_id
                    );
                }

                $share->update(['credited' => true, 'released_at' => now()]);
            }
        });
    }

    /**
     * Cancellation before admin confirmation: funds are still sitting in escrow
     * (en_attente) and no one has been paid yet, so this is always a full,
     * fee-free refund — mirrors ReservationEventController::cancelByUser's
     * 'en_attente_validation' branch.
     */
    public function reverseEscrowOnly(ProgrammeReservation $reservation): void
    {
        if ($reservation->payment_method !== 'wallet') {
            // Nothing was ever collected on-platform for a manual booking that
            // hasn't been paid yet — cancelling it is a pure status change.
            return;
        }

        DB::transaction(function () use ($reservation) {
            Balance::forUser($reservation->user_id)->refundEscrow($reservation->amount_now);
            WalletTransaction::logCredit(
                $reservation->user_id,
                'refund_in',
                $reservation->amount_now, 0, 0, $reservation->amount_now,
                'programme_reservation', $reservation->id,
                "Remboursement annulation (avant confirmation) — réservation programme #{$reservation->id}"
            );
        });
    }

    /**
     * Cancellation after admin confirmation: funds already left escrow and were
     * paid out, so this claws back each owner's exact stored net_amount
     * (never recalculated — see class docblock). For a wallet booking, also
     * credits the camper whatever the cancellation policy allows
     * ($refundToCamper) — for a manual/bank-transfer booking, refunding real
     * money happens out-of-band by the admin, so the platform wallet is never
     * credited (that would hand out money the platform never actually received).
     */
    public function reversePaidOutShares(ProgrammeReservation $reservation, float $refundToCamper): void
    {
        DB::transaction(function () use ($reservation, $refundToCamper) {
            $shares = $reservation->shares()->where('credited', true)->get();

            foreach ($shares as $share) {
                Balance::forUser($share->owner_user_id)->debiter($share->net_amount);
                WalletTransaction::logDebit(
                    $share->owner_user_id,
                    'refund_out',
                    $share->net_amount,
                    'programme_reservation_share',
                    $share->id,
                    "Remboursement annulation — programme #{$reservation->programme_departure_id}",
                    $reservation->user_id
                );
            }

            if ($refundToCamper > 0 && $reservation->payment_method === 'wallet') {
                Balance::forUser($reservation->user_id)->crediter($refundToCamper);
                WalletTransaction::logCredit(
                    $reservation->user_id,
                    'refund_in',
                    $refundToCamper, 0, 0, $refundToCamper,
                    'programme_reservation', $reservation->id,
                    "Remboursement annulation — réservation programme #{$reservation->id}"
                );
            }
        });
    }

    private function resolveCommissionRate(\App\Models\ProgrammeItem $item, int $ownerUserId): float
    {
        if ($item->commission_rate !== null) {
            return $item->commission_rate / 100;
        }

        $customRate = CommissionService::customRateForUser($ownerUserId);
        if ($customRate !== null) {
            return $customRate;
        }

        return CommissionService::rate($item->commissionType());
    }
}
