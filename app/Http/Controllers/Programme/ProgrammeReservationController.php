<?php

namespace App\Http\Controllers\Programme;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\PlatformSetting;
use App\Models\ProgrammeDeparture;
use App\Models\ProgrammeReservation;
use App\Models\PromoCode;
use App\Models\WalletTransaction;
use App\Services\CancellationPolicyService;
use App\Services\CommissionService;
use App\Services\ManualPaymentService;
use App\Services\PaymentReferenceService;
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
            'payment_method' => 'nullable|in:wallet,manual',
            'payment_option' => 'nullable|in:full,deposit',
        ]);

        $paymentMethod = $validated['payment_method'] ?? 'wallet';

        if ($paymentMethod === 'manual' && !ManualPaymentService::isEnabled()) {
            return response()->json(['message' => 'Le paiement manuel n\'est pas disponible actuellement.'], 422);
        }

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

        // ── Payment split ────────────────────────────────────────────────────
        // Programme is an admin-curated bundle, not owned by a single provider,
        // so deposit eligibility uses the platform-wide settings directly
        // rather than a per-provider ProviderPaymentPreference (which doesn't
        // make sense when N different actors are bundled together).
        $paymentOption = 'full';
        $amountNow = $totalToPay;
        $amountLater = 0.0;
        $balanceDueAt = null;

        if ($paymentMethod === 'manual') {
            $minTotal = (int) PlatformSetting::get('deposit_min_total', 150);
            $minPct = (int) PlatformSetting::get('deposit_min_percentage', 20);
            $maxPct = (int) PlatformSetting::get('deposit_max_percentage', 80);
            $requestedOption = $validated['payment_option'] ?? 'full';

            if ($requestedOption === 'deposit') {
                if ($totalToPay < $minTotal) {
                    return response()->json(['message' => "L'acompte n'est possible qu'à partir de {$minTotal} TND."], 422);
                }
                $pct = max($minPct, min($maxPct, 50));
                $paymentOption = 'deposit';
                $amountNow = round($totalToPay * $pct / 100, 2);
                $amountLater = round($totalToPay - $amountNow, 2);

                $due = $requestedDate->copy()->subDays(7);
                $balanceDueAt = $due->isPast() ? now()->addDays(2)->toDateString() : $due->toDateString();
            }
        } else {
            $balance = Balance::forUser($user->id);
            if ($balance->solde_disponible < $totalToPay) {
                return response()->json(['message' => "Solde wallet insuffisant. Disponible : {$balance->solde_disponible} TND, requis : {$totalToPay} TND."], 422);
            }
        }

        $reservation = DB::transaction(function () use (
            $departure, $validated, $user, $totalToPay, $promoCodeId, $selectedItems,
            $paymentMethod, $paymentOption, $amountNow, $amountLater, $balanceDueAt
        ) {
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
                'payment_method' => $paymentMethod,
                'payment_option' => $paymentOption,
                'amount_now' => $amountNow,
                'amount_later' => $amountLater,
                'balance_due_at' => $balanceDueAt,
                'status' => $paymentMethod === 'manual' ? 'pending_payment' : 'pending',
            ]);

            $reservation->selectedItems()->attach($selectedItems->pluck('id'));

            if ($paymentMethod === 'manual') {
                $reservation->payment_reference = PaymentReferenceService::forReservation($reservation->id);
                $reservation->save();
            } else {
                Balance::forUser($user->id)->lockFunds($totalToPay);
                WalletTransaction::logDebit(
                    $user->id, 'reservation_payment', $totalToPay,
                    'programme_reservation', $reservation->id,
                    "Paiement réservation programme #{$reservation->id} (en attente de confirmation)"
                );
            }

            $this->ledger->createShares($reservation);

            return $reservation;
        });

        ProgrammeNotifier::notify(
            $user->id,
            $paymentMethod === 'manual' ? 'Réservation créée — paiement à confirmer' : 'Demande de réservation reçue',
            $paymentMethod === 'manual'
                ? "Votre réservation pour \"{$departure->programme->title}\" est créée. Effectuez votre virement (référence {$reservation->payment_reference}) puis confirmez-le sur la page de la réservation."
                : "Votre demande pour \"{$departure->programme->title}\" a bien été reçue et sera examinée selon la disponibilité réelle. Vous serez notifié dès confirmation.",
            'status_update',
            'medium',
            "/programme-details/{$departure->programme->slug}"
        );

        $payload = ['reservation' => $reservation->load('shares')];
        if ($paymentMethod === 'manual') {
            $payload['payment_info'] = [
                'reference' => $reservation->payment_reference,
                'option' => $paymentOption,
                'amount_now' => $amountNow,
                'amount_later' => $amountLater,
                'balance_due_at' => $balanceDueAt,
                'flouci_link' => ManualPaymentService::flouciLink(),
            ];
        }

        return response()->json($payload, 201);
    }

    // GET /programmes/reservations/{id}/payment-info
    public function paymentInfo(Request $request, int $id)
    {
        $reservation = ProgrammeReservation::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json([
            'reference' => $reservation->payment_reference,
            'option' => $reservation->payment_option,
            'amount_now' => $reservation->amount_now,
            'amount_later' => $reservation->amount_later,
            'balance_due_at' => $reservation->balance_due_at,
            'status' => $reservation->status,
            'flouci_link' => ManualPaymentService::flouciLink(),
        ]);
    }

    // POST /programmes/reservations/{id}/payment-submitted
    public function submitPayment(Request $request, int $id)
    {
        $reservation = ProgrammeReservation::with('departure.programme')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($reservation->payment_method !== 'manual') {
            return response()->json(['message' => "Cette réservation n'utilise pas le paiement manuel."], 422);
        }

        if (is_null($reservation->payment_confirmed_at) && in_array($reservation->status, ['pending_payment', 'paiement_invalide'])) {
            $reservation->update(['status' => 'paiement_soumis', 'payment_submitted_at' => now()]);

            return response()->json(['message' => 'Paiement soumis pour vérification.', 'reservation' => $reservation->fresh()]);
        }

        if ((float) $reservation->amount_later > 0 && $reservation->status === 'confirmed') {
            $reservation->update(['status' => 'solde_soumis', 'payment_submitted_at' => now()]);

            return response()->json(['message' => 'Solde soumis pour vérification.', 'reservation' => $reservation->fresh()]);
        }

        return response()->json(['message' => 'Aucun paiement en attente pour cette réservation.'], 422);
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

        $cancellable = ['pending_payment', 'paiement_soumis', 'paiement_invalide', 'pending', 'confirmed'];
        if (!in_array($reservation->status, $cancellable)) {
            return response()->json(['message' => 'Cette réservation ne peut plus être annulée.'], 422);
        }

        DB::transaction(function () use ($reservation) {
            if ($reservation->status === 'confirmed') {
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
            } else {
                // pending_payment / paiement_soumis / paiement_invalide / pending —
                // no payout has happened yet (no-op for manual bookings not yet paid).
                $this->ledger->reverseEscrowOnly($reservation);
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
