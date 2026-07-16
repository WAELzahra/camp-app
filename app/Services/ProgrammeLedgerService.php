<?php

namespace App\Services;

use App\Models\AdminWalletTransaction;
use App\Models\Balance;
use App\Models\ProgrammeReservation;
use App\Models\ProgrammeReservationShare;
use App\Models\ProgrammeStepPartner;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Generalizes the EventReservationMaterial escrow → confirm → payout → reverse
 * pattern (see ReservationEventController::updateStatus/cancelByUser/cancelReservation)
 * to an arbitrary number of partners per reservation instead of just suppliers.
 */
class ProgrammeLedgerService
{
    /**
     * Split a reservation's escrowed amount across every partner attached to
     * the booked departure's programme steps, proportional to each partner's
     * price and the actual amount collected (so shares always sum to what the
     * camper paid, even after a promo code discount).
     */
    public function createShares(ProgrammeReservation $reservation): void
    {
        $departure = $reservation->departure()->with('programme.steps.stepPartners.partner')->first();
        $programme = $departure->programme;

        $stepPartners = $programme->steps->flatMap->stepPartners;
        $rawBase = (float) $stepPartners->sum('price') * $reservation->participants_count;
        $ratio = $rawBase > 0 ? $reservation->amount_now / $rawBase : 0;

        foreach ($stepPartners as $stepPartner) {
            $gross = round($stepPartner->price * $reservation->participants_count * $ratio, 2);
            if ($gross <= 0) {
                continue;
            }

            $rate = $this->resolveCommissionRate($stepPartner);
            $commission = round($gross * $rate, 2);
            $net = round($gross - $commission, 2);

            ProgrammeReservationShare::create([
                'programme_reservation_id' => $reservation->id,
                'partner_id' => $stepPartner->partner_id,
                'programme_step_partner_id' => $stepPartner->id,
                'gross_amount' => $gross,
                'commission_rate' => round($rate * 100, 2),
                'commission_amount' => $commission,
                'net_amount' => $net,
                'partner_credited' => false,
            ]);
        }
    }

    /**
     * Admin-triggered confirmation (V1 has no per-partner accept workflow):
     * releases the camper's escrow and credits every partner their net share.
     * Partners without a platform account (user_id null) are marked credited
     * for traceability only — their payout happens off-platform for now.
     */
    public function payoutShares(ProgrammeReservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            Balance::forUser($reservation->user_id)->releaseEscrow($reservation->amount_now);

            $shares = $reservation->shares()->where('partner_credited', false)->with('partner')->get();

            foreach ($shares as $share) {
                $partner = $share->partner;

                if ($partner->user_id) {
                    Balance::forUser($partner->user_id)->crediter($share->net_amount);
                    WalletTransaction::logCredit(
                        $partner->user_id,
                        'reservation_income',
                        $share->gross_amount,
                        $share->commission_rate,
                        $share->commission_amount,
                        $share->net_amount,
                        'programme_reservation_share',
                        $share->id,
                        "Revenu programme #{$reservation->programme_departure_id} — {$partner->name}",
                        $reservation->user_id
                    );

                    if ($share->commission_amount > 0) {
                        AdminWalletTransaction::log(
                            'commission',
                            $share->commission_amount,
                            'programme_reservation_share',
                            $share->id,
                            "Commission partenaire — {$partner->name}",
                            $partner->user_id
                        );
                    }
                }

                $share->update(['partner_credited' => true, 'released_at' => now()]);
            }
        });
    }

    /**
     * Cancellation before admin confirmation: funds are still sitting in escrow
     * (en_attente) and no partner has been paid yet, so this is always a full,
     * fee-free refund — mirrors ReservationEventController::cancelByUser's
     * 'en_attente_validation' branch.
     */
    public function reverseEscrowOnly(ProgrammeReservation $reservation): void
    {
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
     * paid out to partners, so this claws back each partner's exact stored
     * net_amount (never recalculated — see class docblock) and credits the
     * camper whatever the cancellation policy allows ($refundToCamper).
     */
    public function reversePaidOutShares(ProgrammeReservation $reservation, float $refundToCamper): void
    {
        DB::transaction(function () use ($reservation, $refundToCamper) {
            $shares = $reservation->shares()->where('partner_credited', true)->with('partner')->get();

            foreach ($shares as $share) {
                $partner = $share->partner;
                if (!$partner->user_id) {
                    continue;
                }

                Balance::forUser($partner->user_id)->debiter($share->net_amount);
                WalletTransaction::logDebit(
                    $partner->user_id,
                    'refund_out',
                    $share->net_amount,
                    'programme_reservation_share',
                    $share->id,
                    "Remboursement annulation — programme #{$reservation->programme_departure_id}",
                    $reservation->user_id
                );
            }

            if ($refundToCamper > 0) {
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

    private function resolveCommissionRate(ProgrammeStepPartner $stepPartner): float
    {
        $partner = $stepPartner->partner;

        if ($partner->user_id) {
            $customRate = CommissionService::customRateForUser($partner->user_id);
            if ($customRate !== null) {
                return $customRate;
            }
        }

        return ($stepPartner->commission_rate ?? $partner->default_commission_rate) / 100;
    }
}
