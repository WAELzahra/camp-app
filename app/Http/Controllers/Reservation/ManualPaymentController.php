<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Models\ProviderPaymentPreference;
use App\Models\Reservations_events;
use App\Models\Reservations_centre;
use App\Models\Reservations_materielles;
use App\Services\ManualPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PlatformSetting as PS;

/**
 * Handles the camper-side of the manual payment lifecycle and provider
 * deposit-preference management (Phase 1 + 1.5).
 */
class ManualPaymentController extends Controller
{
    /**
     * POST /my/reservations/{type}/{id}/payment-submitted
     * Camper declares they have completed the bank transfer.
     * type: events | centres | materielles
     */
    public function submitProof(string $type, int $id): JsonResponse
    {
        $reservation = $this->findOwn($type, $id);
        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable.'], 404);
        }

        if ($reservation->payment_method !== 'manual') {
            return response()->json(['message' => 'Cette réservation n\'utilise pas le paiement manuel.'], 422);
        }

        // Initial payment: pending or after rejection
        if (in_array($reservation->status, ['pending', 'paiement_invalide'])) {
            $reservation->status               = 'paiement_soumis';
            $reservation->payment_submitted_at = now();
            $reservation->save();

            return response()->json([
                'message' => 'Paiement soumis. Un administrateur va vérifier votre virement sous peu.',
                'status'  => 'paiement_soumis',
            ]);
        }

        // Balance payment: deposit confirmed, camper now submits the remaining balance
        if ($reservation->status === 'confirmée_solde_en_attente') {
            $reservation->status               = 'solde_soumis';
            $reservation->payment_submitted_at = now();
            $reservation->save();

            return response()->json([
                'message' => 'Solde soumis. Un administrateur va vérifier votre virement sous peu.',
                'status'  => 'solde_soumis',
            ]);
        }

        return response()->json([
            'message' => 'Le paiement ne peut pas être soumis pour ce statut.',
            'status'  => $reservation->status,
        ], 422);
    }

    /**
     * GET /my/reservations/{type}/{id}/payment-info
     * Returns payment reference, Flouci link, and amounts for the payment screen.
     */
    public function paymentInfo(string $type, int $id): JsonResponse
    {
        $reservation = $this->findOwn($type, $id);
        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable.'], 404);
        }

        if ($reservation->payment_method !== 'manual') {
            return response()->json(['message' => 'Cette réservation n\'utilise pas le paiement manuel.'], 422);
        }

        // For the balance step, amount_now is the remaining balance
        $isBalanceStep = $reservation->status === 'confirmée_solde_en_attente';

        return response()->json([
            'reference'      => $reservation->payment_reference,
            'option'         => $reservation->payment_option,
            'amount_now'     => $isBalanceStep ? $reservation->amount_later : $reservation->amount_now,
            'amount_later'   => $isBalanceStep ? 0 : $reservation->amount_later,
            'balance_due_at' => $reservation->balance_due_at,
            'flouci_link'    => ManualPaymentService::flouciLink(),
            'status'         => $reservation->status,
            'is_balance_step'=> $isBalanceStep,
        ]);
    }

    // ── Provider payment preferences ─────────────────────────────────────────

    /**
     * GET /providers/{userId}/payment-preferences
     * Returns a provider's public-facing deposit preferences (no auth required).
     * Campers use this to know whether deposits are available when booking.
     */
    public function providerPreferences(int $userId): JsonResponse
    {
        $pref    = ProviderPaymentPreference::forUser($userId);
        $minPct  = (int) PS::get('deposit_min_percentage', 20);
        $maxPct  = (int) PS::get('deposit_max_percentage', 80);
        $minTotal= (int) PS::get('deposit_min_total', 150);

        return response()->json([
            'accepts_deposits'   => $pref->accepts_deposits,
            'deposit_percentage' => $pref->deposit_percentage,
            'deposit_min_total'  => $minTotal,
            'min_percentage'     => $minPct,
            'max_percentage'     => $maxPct,
        ]);
    }

    /**
     * GET /my/payment-preferences
     * Returns the authenticated provider's deposit preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $userId = Auth::id();
        $pref   = ProviderPaymentPreference::forUser($userId);

        $minPct = (int) PS::get('deposit_min_percentage', 20);
        $maxPct = (int) PS::get('deposit_max_percentage', 80);

        return response()->json([
            'accepts_deposits'   => $pref->accepts_deposits,
            'deposit_percentage' => $pref->deposit_percentage,
            'min_percentage'     => $minPct,
            'max_percentage'     => $maxPct,
        ]);
    }

    /**
     * PUT /my/payment-preferences
     * Updates the authenticated provider's deposit preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $minPct = (int) PS::get('deposit_min_percentage', 20);
        $maxPct = (int) PS::get('deposit_max_percentage', 80);

        $validated = $request->validate([
            'accepts_deposits'   => 'required|boolean',
            'deposit_percentage' => "required_if:accepts_deposits,true|integer|min:{$minPct}|max:{$maxPct}",
        ]);

        $pref = ProviderPaymentPreference::forUser(Auth::id());
        $pref->update([
            'accepts_deposits'   => $validated['accepts_deposits'],
            'deposit_percentage' => $validated['deposit_percentage'] ?? $pref->deposit_percentage,
        ]);

        return response()->json([
            'message'            => 'Préférences de paiement mises à jour.',
            'accepts_deposits'   => $pref->accepts_deposits,
            'deposit_percentage' => $pref->deposit_percentage,
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function findOwn(string $type, int $id): mixed
    {
        $userId = Auth::id();
        return match($type) {
            'events'     => Reservations_events::where('id', $id)->where('user_id', $userId)->first(),
            'centres'    => Reservations_centre::where('id', $id)->where('user_id', $userId)->first(),
            'materielles'=> Reservations_materielles::where('id', $id)->where('user_id', $userId)->first(),
            default      => null,
        };
    }
}
