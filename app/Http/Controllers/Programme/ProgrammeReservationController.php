<?php

namespace App\Http\Controllers\Programme;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\ProgrammeDeparture;
use App\Models\ProgrammeReservation;
use App\Models\PromoCode;
use App\Models\WalletTransaction;
use App\Services\CancellationPolicyService;
use App\Services\CommissionService;
use App\Services\ProgrammeLedgerService;
use App\Services\ProgrammeNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProgrammeReservationController extends Controller
{
    public function __construct(private ProgrammeLedgerService $ledger)
    {
    }

    // POST /programmes/{id}/reservations
    // V1: wallet payment only, full payment only (no deposit/manual — see plan's stated simplifications).
    public function store(Request $request, int $id)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'programme_departure_id' => 'required|exists:programme_departures,id',
            'requested_date' => 'required|date|after_or_equal:today',
            'participants_count' => 'required|integer|min:1',
            'promo_code' => 'nullable|string|max:50',
            'selected_item_ids' => 'required|array|min:1',
            'selected_item_ids.*' => 'integer',
        ]);

        $departure = ProgrammeDeparture::with('programme.items')->findOrFail($validated['programme_departure_id']);

        if ($departure->programme_id !== $id || $departure->programme->status !== 'published') {
            return response()->json(['message' => 'Programme introuvable.'], 404);
        }

        if ($departure->status !== 'open') {
            return response()->json(['message' => 'Ce départ n\'est plus ouvert aux réservations.'], 422);
        }

        $requestedDate = \Carbon\Carbon::parse($validated['requested_date'])->startOfDay();
        $windowStart = \Carbon\Carbon::parse($departure->start_date)->startOfDay();
        $windowEnd = $departure->end_date ? \Carbon\Carbon::parse($departure->end_date)->startOfDay() : null;
        if ($requestedDate->lt($windowStart) || ($windowEnd && $requestedDate->gt($windowEnd))) {
            return response()->json(['message' => 'La date choisie est en dehors de la période disponible pour ce programme.'], 422);
        }

        if ($departure->seatsRemaining() < $validated['participants_count']) {
            return response()->json(['message' => 'Places restantes insuffisantes.'], 422);
        }

        $selectedItems = $departure->programme->items->whereIn('id', $validated['selected_item_ids']);
        if ($selectedItems->count() !== count($validated['selected_item_ids'])) {
            return response()->json(['message' => 'Sélection invalide.'], 422);
        }

        // A departure's flat price_override only applies to the full bundle —
        // partial selections are always priced item by item.
        $allItemsSelected = $selectedItems->count() === $departure->programme->items->count();
        $pricePerParticipant = ($allItemsSelected && $departure->price_override !== null)
            ? (float) $departure->price_override
            : (float) $selectedItems->sum('price');

        $basePrice = $pricePerParticipant * $validated['participants_count'];

        $promoCodeId = null;
        $discountAmount = 0;
        if (!empty($validated['promo_code'])) {
            $promo = PromoCode::where('code', $validated['promo_code'])->first();
            if (!$promo) {
                return response()->json(['message' => 'Code promo invalide.'], 422);
            }
            $check = $promo->isValid('programme', $basePrice);
            if (!$check['valid']) {
                return response()->json(['message' => $check['reason']], 422);
            }
            $discountAmount = $promo->calculateDiscount($basePrice);
            $promoCodeId = $promo->id;
        }

        $feeData = CommissionService::addServiceFee(max(0, round($basePrice - $discountAmount, 2)));
        $totalToPay = $feeData['total'];

        $balance = Balance::forUser($user->id);
        if ($balance->solde_disponible < $totalToPay) {
            return response()->json(['message' => "Solde wallet insuffisant. Disponible : {$balance->solde_disponible} TND, requis : {$totalToPay} TND."], 422);
        }

        $reservation = DB::transaction(function () use ($departure, $validated, $user, $totalToPay, $promoCodeId, $balance, $selectedItems) {
            $departure->increment('capacity_booked', $validated['participants_count']);
            if ($departure->seatsRemaining() === 0) {
                $departure->update(['status' => 'full']);
            }

            if ($promoCodeId) {
                PromoCode::find($promoCodeId)?->incrementUsage();
            }

            $reservation = ProgrammeReservation::create([
                'programme_departure_id' => $departure->id,
                'requested_date' => $validated['requested_date'],
                'user_id' => $user->id,
                'participants_count' => $validated['participants_count'],
                'total_price' => $totalToPay,
                'payment_method' => 'wallet',
                'payment_option' => 'full',
                'amount_now' => $totalToPay,
                'amount_later' => null,
                'status' => 'pending',
                'promo_code_id' => $promoCodeId,
            ]);

            $reservation->selectedItems()->attach($selectedItems->pluck('id'));

            $balance->lockFunds($totalToPay);
            WalletTransaction::logDebit(
                $user->id, 'reservation_payment', $totalToPay,
                'programme_reservation', $reservation->id,
                "Paiement réservation programme #{$reservation->id} (en attente de confirmation)"
            );

            $this->ledger->createShares($reservation);

            return $reservation;
        });

        ProgrammeNotifier::notify(
            $user->id,
            'Demande de réservation reçue',
            "Votre demande pour \"{$departure->programme->title}\" a bien été reçue et sera examinée selon la disponibilité réelle. Vous serez notifié dès confirmation.",
            'status_update',
            'medium',
            "/programme-details/{$departure->programme->slug}"
        );

        return response()->json(['reservation' => $reservation->load('shares')], 201);
    }

    // GET /my/programme-reservations
    public function mine(Request $request)
    {
        $reservations = ProgrammeReservation::where('user_id', $request->user()->id)
            ->with(['departure.programme', 'selectedItems'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['reservations' => $reservations]);
    }

    // POST /programmes/reservations/{id}/cancel
    public function cancel(Request $request, int $id)
    {
        $user = $request->user();
        $reservation = ProgrammeReservation::with('departure.programme')->where('user_id', $user->id)->findOrFail($id);

        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Cette réservation ne peut plus être annulée.'], 422);
        }

        DB::transaction(function () use ($reservation) {
            if ($reservation->status === 'pending') {
                $this->ledger->reverseEscrowOnly($reservation);
            } else {
                $programme = $reservation->departure->programme;
                $policyResult = CancellationPolicyService::preview(
                    'programme',
                    \Carbon\Carbon::parse($reservation->departure->start_date),
                    (float) $reservation->amount_now,
                    null,
                    \Carbon\Carbon::parse($reservation->created_at)
                );
                $refund = $policyResult ? (float) $policyResult['refund_amount'] : (float) $reservation->amount_now;
                $this->ledger->reversePaidOutShares($reservation, $refund);
            }

            $reservation->departure->decrement('capacity_booked', $reservation->participants_count);
            if ($reservation->departure->status === 'full') {
                $reservation->departure->update(['status' => 'open']);
            }

            $reservation->update(['status' => 'cancelled']);
        });

        ProgrammeNotifier::notify(
            $user->id,
            'Réservation annulée',
            "Votre réservation pour \"{$reservation->departure->programme->title}\" a été annulée.",
            'reservation_cancelled',
            'medium'
        );

        return response()->json(['message' => 'Réservation annulée.', 'reservation' => $reservation->fresh()]);
    }
}
