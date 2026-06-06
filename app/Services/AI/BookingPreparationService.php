<?php

namespace App\Services\AI;

use App\Models\CampingCentre;
use App\Models\CancellationPolicy;
use App\Models\Materielles;
use App\Models\Reservations_centre;
use App\Models\User;
use App\Services\AI\Booking\BookingSummary;
use App\Services\CommissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Validates, prices, and structures a booking for user review.
 *
 * Hard constraints:
 *   - Does NOT create any DB records.
 *   - Does NOT trigger any payment.
 *   - Returns a BookingSummary the user must explicitly confirm.
 */
class BookingPreparationService
{
    /**
     * Build a BookingSummary from the last recommendation result and the
     * current conversation state.
     *
     * Steps:
     *   1. Identify what is being booked (zone | centre_partner | centre_external)
     *   2. Propose check-in / check-out dates (+7 days from today)
     *   3. For partner centres: validate availability, capacity, date conflicts
     *   4. Validate each gear item's current DB stock
     *   5. Price accommodation + gear
     *   6. Add platform service fee
     *   7. Return BookingSummary DTO (is_bookable = false for external centres,
     *      unavailable centres, and date conflicts)
     */
    public function prepare(
        array $recommendationResult,
        array $conversationState,
        User  $user,
    ): BookingSummary {
        // ── Step 1: Identify recommendation type ─────────────────────────────
        // TripPlannerService uses 'recommended_zone' for zones and 'recommended'
        // for centre results. Normalise to a single variable.
        $recommended = $recommendationResult['recommended']
                    ?? $recommendationResult['recommended_zone']
                    ?? null;

        $recType = $recommended['type'] ?? null;

        if ($recType === 'centre_external') {
            return $this->notBookable(
                'Ce centre n\'est pas encore partenaire de TunisiaCamp. '
                . 'Contactez-les directement.'
            );
        }

        if ($recType === null || isset($recommendationResult['error'])) {
            return $this->notBookable(
                'Aucune recommandation disponible. Veuillez d\'abord obtenir une recommandation.'
            );
        }

        $nights    = max(1, (int) ($conversationState['duration_nights'] ?? 2));
        $groupSize = max(1, (int) ($conversationState['group_size'] ?? 1));

        // ── Step 2: Propose dates ─────────────────────────────────────────────
        $checkIn       = Carbon::now()->addDays(7)->startOfDay();
        $checkOut      = $checkIn->copy()->addDays($nights);
        $checkInStr    = $checkIn->format('Y-m-d');
        $checkOutStr   = $checkOut->format('Y-m-d');
        $datesProposed = true;

        // ── Step 3: Accommodation — centre_partner only ───────────────────────
        $centreId           = null;
        $centreUserId       = null;
        $centreNom          = null;
        $accommodationTotal = 0.0;
        $bookingType        = 'zone_no_gear';  // overwritten below

        if ($recType === 'centre_partner') {
            $campingCentreId = $recommended['id'] ?? null;

            if (! $campingCentreId) {
                return $this->notBookable('Identifiant du centre manquant dans la recommandation.');
            }

            $campingCentre = CampingCentre::with('profileCentre')->find($campingCentreId);

            if (! $campingCentre) {
                return $this->notBookable('Centre introuvable dans la base de données.');
            }

            $profileCentre = $campingCentre->profileCentre;

            if (! $profileCentre || ! $profileCentre->disponibilite) {
                return $this->notBookable(
                    'Ce centre n\'est pas disponible actuellement. '
                    . 'Essayez une autre date ou choisissez un autre centre.'
                );
            }

            if ($campingCentre->user_id === null) {
                return $this->notBookable(
                    'Ce centre n\'a pas encore de compte partenaire actif sur TunisiaCamp.'
                );
            }

            if ($profileCentre->capacite !== null && $profileCentre->capacite < $groupSize) {
                return $this->notBookable(
                    "La capacité du centre ({$profileCentre->capacite} personnes) est "
                    . "insuffisante pour votre groupe ({$groupSize} personnes)."
                );
            }

            // Conflict check: any approved reservation that overlaps the proposed dates
            $conflict = Reservations_centre::where('centre_id', $campingCentre->user_id)
                ->where('status', 'approved')
                ->where('date_debut', '<', $checkOutStr)
                ->where('date_fin',   '>', $checkInStr)
                ->exists();

            if ($conflict) {
                return $this->notBookable(
                    "Le centre est déjà complet pour les dates proposées "
                    . "({$checkInStr} au {$checkOutStr}). Veuillez choisir d'autres dates."
                );
            }

            $centreId           = $campingCentre->id;
            $centreUserId       = $campingCentre->user_id;
            $centreNom          = $campingCentre->nom ?? ($recommended['nom'] ?? '');
            // Accommodation is priced per-stay (price × nights; NOT × group_size)
            $accommodationTotal = round((float) ($profileCentre->price_per_night ?? 0) * $nights, 2);
            $bookingType        = 'centre_partner';
        }

        // ── Step 4: Validate and price gear ───────────────────────────────────
        $gearItems       = [];
        $unavailableGear = [];
        $gearTotal       = 0.0;

        foreach ($recommendationResult['gear_list'] ?? [] as $item) {
            $id = $item['id'] ?? null;
            if (! $id) {
                continue;
            }

            $materielle = Materielles::find($id);

            if (! $materielle || $materielle->status !== 'up' || $materielle->quantite_dispo < 1) {
                $unavailableGear[] = [
                    'id'     => $id,
                    'nom'    => $item['nom'] ?? '',
                    'reason' => 'Indisponible',
                ];
                continue;
            }

            // Cost per item = tarif_nuit × nights (NOT multiplied by group_size)
            $itemTotal = round((float) $materielle->tarif_nuit * $nights, 2);

            $gearItems[] = [
                'id'             => $id,
                'nom'            => $materielle->nom,
                'brand'          => $materielle->brand ?? '',
                'tarif_nuit'     => (float) $materielle->tarif_nuit,
                'item_total'     => $itemTotal,
                'fournisseur_id' => $materielle->fournisseur_id,
                'category'       => $item['category'] ?? '',
            ];
            $gearTotal += $itemTotal;
        }

        $gearTotal = round($gearTotal, 2);

        // Determine zone booking sub-type
        if ($recType === 'zone') {
            $bookingType = ! empty($gearItems) ? 'zone_with_gear' : 'zone_no_gear';
        }

        // A free natural zone with no gear to rent has nothing to book.
        // The booking card would be empty and misleading — return not-bookable
        // so the frontend omits the booking card for this case.
        if ($bookingType === 'zone_no_gear') {
            return $this->notBookable(
                'Cette zone naturelle est en accès libre — aucune réservation requise. '
                . 'Vous pouvez ajouter du matériel à louer si nécessaire.'
            );
        }

        // ── Step 5 & 6: Totals with platform service fee ──────────────────────
        $subtotal      = round($accommodationTotal + $gearTotal, 2);
        $feeRate       = $this->platformFeeRate();
        $platformFee   = round($subtotal * $feeRate, 2);
        $total         = round($subtotal + $platformFee, 2);

        // ── Meta ──────────────────────────────────────────────────────────────
        $preparedAt = Carbon::now();
        $expiresAt  = $preparedAt->copy()->addMinutes(15);

        return new BookingSummary(
            is_bookable:         true,
            booking_type:        $bookingType,
            not_bookable_reason: null,
            centre_id:           $centreId,
            centre_user_id:      $centreUserId,
            centre_nom:          $centreNom,
            check_in:            $checkInStr,
            check_out:           $checkOutStr,
            dates_are_proposed:  $datesProposed,
            nbr_place:           $groupSize,
            accommodation_total: $accommodationTotal,
            gear_items:          $gearItems,
            unavailable_gear:    $unavailableGear,
            gear_total:          $gearTotal,
            subtotal:            $subtotal,
            platform_fee:        $platformFee,
            platform_fee_rate:   $feeRate,
            total:               $total,
            currency:            'TND',
            cancellation_note:   $this->cancellationNote(),
            prepared_at:         $preparedAt->toIso8601String(),
            expires_at:          $expiresAt->toIso8601String(),
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function notBookable(string $reason): BookingSummary
    {
        $now = Carbon::now();

        return new BookingSummary(
            is_bookable:         false,
            booking_type:        'not_bookable',
            not_bookable_reason: $reason,
            centre_id:           null,
            centre_user_id:      null,
            centre_nom:          null,
            check_in:            null,
            check_out:           null,
            dates_are_proposed:  false,
            nbr_place:           null,
            accommodation_total: 0.0,
            gear_items:          [],
            unavailable_gear:    [],
            gear_total:          0.0,
            subtotal:            0.0,
            platform_fee:        0.0,
            platform_fee_rate:   0.0,
            total:               0.0,
            currency:            'TND',
            cancellation_note:   null,
            prepared_at:         $now->toIso8601String(),
            expires_at:          $now->copy()->addMinutes(15)->toIso8601String(),
        );
    }

    /**
     * The service fee rate charged to the camper (on top of the base price).
     * Reads 'service_fee_camper' from platform_settings (stored as integer %).
     * Falls back to 5% and logs a warning if the setting is missing.
     */
    private function platformFeeRate(): float
    {
        try {
            $rate = CommissionService::serviceFeeRate(); // returns decimal, e.g. 0.03
            if ($rate > 0) {
                return $rate;
            }
        } catch (\Throwable) {
            // setting read failure
        }

        Log::warning('booking_platform_fee_rate_missing', [
            'source'   => 'service_fee_camper',
            'fallback' => 0.05,
        ]);

        return 0.05;
    }

    /**
     * Human-readable cancellation note.
     * Tries to load from the first active CancellationPolicy; falls back to
     * a generic message when the table is empty or unavailable.
     */
    private function cancellationNote(): string
    {
        try {
            $policy = CancellationPolicy::where('is_active', true)->first();
            if ($policy && $policy->name) {
                $grace = $policy->grace_period_hours ?? 48;
                return "Politique : {$policy->name}. Annulation gratuite jusqu'à {$grace}h avant le début du séjour.";
            }
        } catch (\Throwable) {
            // Table may not exist or be empty in test environments
        }

        return "Annulation gratuite jusqu'à 48h avant le début du séjour. "
             . 'Des frais peuvent s\'appliquer au-delà.';
    }
}
