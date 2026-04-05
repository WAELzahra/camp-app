<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Reservations_materielles;
use App\Models\Materielles;
use App\Models\User;
use App\Models\PromoCode;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewReservationToFournisseur;
use App\Mail\ReservationConfirmedToCamper;
use App\Mail\ReservationCanceledToFournisseur;
use App\Mail\ReservationRejectedToUser;
use App\Mail\ReservationCanceledByFournisseurToCamper;
use App\Mail\RentalReturnConfirmed;
use App\Mail\ReservationDisputedToCamper;
use App\Mail\ReservationDisputedToFournisseur;

class ReservationMaterielleController extends Controller
{
    // -------------------------------------------------------------------------
    // READ
    // -------------------------------------------------------------------------

    public function index(int $idMaterielle)
    {
        $fournisseurId = Auth::id();
        $reservations = Reservations_materielles::with(['user', 'materielle'])
            ->where('materielle_id', $idMaterielle)
            ->where('fournisseur_id', $fournisseurId)
            ->get();
        if ($reservations->isEmpty()) {
            return response()->json(['message' => 'No reservations found for this materielle.'], 404);
        }
        return response()->json(['message' => 'Reservations retrieved successfully.', 'reservations' => $reservations]);
    }

    public function index_user()
    {
        $reservations = Reservations_materielles::with(['materielle', 'fournisseur'])
            ->where('user_id', Auth::id())
            ->get();
        if ($reservations->isEmpty()) {
            return response()->json(['message' => 'No reservations found for this user.'], 404);
        }
        return response()->json(['message' => 'User reservations retrieved successfully.', 'reservations' => $reservations]);
    }

    public function show()
    {
        $reservations = Reservations_materielles::with(['user', 'materielle'])
            ->where('fournisseur_id', Auth::id())
            ->get();
        if ($reservations->isEmpty()) {
            return response()->json(['message' => 'No reservations found for this provider.'], 404);
        }
        return response()->json(['message' => 'Provider reservations retrieved successfully.', 'reservations' => $reservations]);
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    public function store(Request $request)
    {
        $validated = $request->validate([
            'materielle_id'     => 'required|exists:materielles,id',
            'fournisseur_id'    => 'required|exists:users,id',
            'type_reservation'  => 'required|in:location,achat',
            'date_debut'        => 'required_if:type_reservation,location|nullable|date|after_or_equal:today',
            'date_fin'          => 'required_if:type_reservation,location|nullable|date|after_or_equal:date_debut',
            'quantite'          => 'required|integer|min:1',
            'montant_total'     => 'required|numeric|min:0',
            'mode_livraison'    => 'required|in:pickup,delivery',
            'adresse_livraison' => 'required_if:mode_livraison,delivery|nullable|string|max:500',
            'frais_livraison'   => 'nullable|numeric|min:0',
            'promo_code'        => 'nullable|string|max:50',
        ]);

        $user     = Auth::user();
        $isRental = $validated['type_reservation'] === 'location';

        if ($isRental) {
            $user->load('profile');
            if (!$user->hasCin()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Your national ID (CIN) is required to make a rental reservation.',
                    'action'  => 'upload_cin',
                ], 422);
            }
        }

        // FIX: Allow both suppliers (role_id: 4) AND campers (role_id: 1) to be equipment providers
        $fournisseur = User::where('id', $validated['fournisseur_id'])
            ->whereIn('role_id', [1, 4])  // 1 = camper, 4 = supplier
            ->first();
            
        if (!$fournisseur) {
            return response()->json(['status' => 'error', 'message' => 'Invalid provider. Equipment owner must be a registered user.'], 422);
        }

        $materielle = Materielles::find($validated['materielle_id']);
        if (!$materielle || $materielle->status === 'down') {
            return response()->json(['status' => 'error', 'message' => 'This equipment is not available.'], 422);
        }
        if ($isRental && !$materielle->is_rentable) {
            return response()->json(['status' => 'error', 'message' => 'This equipment is not available for rental.'], 422);
        }
        if (!$isRental && !$materielle->is_sellable) {
            return response()->json(['status' => 'error', 'message' => 'This equipment is not available for purchase.'], 422);
        }
        if ($materielle->quantite_dispo < $validated['quantite']) {
            return response()->json(['status' => 'error', 'message' => "Insufficient stock. Available: {$materielle->quantite_dispo}."], 422);
        }

        // Apply promo code if provided
        $promoCodeId    = null;
        $discountAmount = 0;
        $montantTotal   = (float) $validated['montant_total'];
        if (!empty($validated['promo_code'])) {
            $promo = PromoCode::where('code', strtoupper(trim($validated['promo_code'])))->first();
            if (!$promo) {
                return response()->json(['status' => 'error', 'message' => 'Invalid promo code.'], 422);
            }
            $check = $promo->isValid('materiel', $montantTotal);
            if (!$check['valid']) {
                return response()->json(['status' => 'error', 'message' => $check['reason']], 422);
            }
            $discountAmount = $promo->calculateDiscount($montantTotal);
            $montantTotal   = max(0, round($montantTotal - $discountAmount, 2));
            $promoCodeId    = $promo->id;
        }

        DB::beginTransaction();
        try {
            $reservation = Reservations_materielles::create([
                'materielle_id'     => $validated['materielle_id'],
                'user_id'           => $user->id,
                'fournisseur_id'    => $validated['fournisseur_id'],
                'type_reservation'  => $validated['type_reservation'],
                'date_debut'        => $validated['date_debut'] ?? null,
                'date_fin'          => $validated['date_fin'] ?? null,
                'quantite'          => $validated['quantite'],
                'montant_total'     => $montantTotal,
                'mode_livraison'    => $validated['mode_livraison'],
                'adresse_livraison' => $validated['adresse_livraison'] ?? null,
                'frais_livraison'   => $validated['frais_livraison'] ?? 0,
                'cin_camper'        => $isRental ? $user->profile->cin_path : null,
                'status'            => 'pending',
                'promo_code_id'     => $promoCodeId,
                'discount_amount'   => $discountAmount,
            ]);

            // Increment promo usage
            if ($promoCodeId) {
                PromoCode::find($promoCodeId)?->incrementUsage();
            }

            DB::commit();

            Mail::to($fournisseur->email)->send(new NewReservationToFournisseur($reservation, $user));

            $policies = $isRental ? [
                'Your CIN has been recorded for this reservation.',
                'The equipment must be returned before the end date of the rental.',
                'Any equipment not returned may be subject to legal action.',
                'Payment will only be released to the provider after return confirmation.',
            ] : [
                'The sale is final. No returns are accepted.',
                'Payment will be released to the provider after delivery is confirmed via your PIN code.',
            ];

            return response()->json([
                'status'      => 'success',
                'message'     => 'Reservation submitted successfully.',
                'reservation' => $reservation,
                'policies'    => $policies,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    // -------------------------------------------------------------------------
    // FOURNISSEUR ACTIONS
    // -------------------------------------------------------------------------

    public function confirm(int $id)
    {
        \Log::info('Confirm attempt', [
            'reservation_id' => $id,
            'auth_user_id'   => Auth::id(),
        ]);

        $reservation = Reservations_materielles::with(['materielle', 'user'])->find($id);
        
        \Log::info('Reservation found', [
            'found'          => !!$reservation,
            'fournisseur_id' => $reservation?->fournisseur_id,
            'status'         => $reservation?->status,
            'auth_matches'   => $reservation?->fournisseur_id === Auth::id(),
        ]);

        if (!$reservation) return response()->json(['message' => 'Reservation not found.'], 404);
        if ($reservation->fournisseur_id !== Auth::id()) return response()->json(['message' => 'Unauthorized.'], 403);
        if ($reservation->status !== 'pending') return response()->json(['message' => 'Only pending reservations can be confirmed.'], 400);

        $materielle = $reservation->materielle;
        
        \Log::info('Materielle check', [
            'materielle_found' => !!$materielle,
            'quantite_dispo'   => $materielle?->quantite_dispo,
            'quantite_needed'  => $reservation->quantite,
        ]);

        if ($materielle->quantite_dispo < $reservation->quantite) {
            return response()->json(['message' => "Insufficient stock. Available: {$materielle->quantite_dispo}."], 400);
        }

        DB::beginTransaction();
        try {
            $materielle->decrement('quantite_dispo', $reservation->quantite);
            $reservation->status       = 'confirmed';
            $reservation->confirmed_at = now();
            $reservation->save();
            $rawPin = $reservation->generatePin();
            DB::commit();

            \Log::info('Reservation confirmed, sending email', [
                'camper_email' => $reservation->user->email,
                'pin_length'   => strlen($rawPin),
            ]);

            Mail::to($reservation->user->email)->send(new ReservationConfirmedToCamper($reservation, $rawPin));

            return response()->json([
                'message'     => 'Reservation confirmed. PIN has been sent to the camper by email.',
                'reservation' => $reservation,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Confirm failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Error confirming reservation: ' . $e->getMessage()], 500);
        }
    }
    public function reject(int $id)
    {
        $reservation = Reservations_materielles::find($id);
        if (!$reservation) return response()->json(['message' => 'Reservation not found.'], 404);
        if ($reservation->fournisseur_id !== Auth::id()) return response()->json(['message' => 'Unauthorized.'], 403);
        if ($reservation->status !== 'pending') return response()->json(['message' => 'Only pending reservations can be rejected.'], 400);

        $reservation->update(['status' => 'rejected']);

        $camper = User::find($reservation->user_id);
        if ($camper) {
            Mail::to($camper->email)->send(new ReservationRejectedToUser($camper, 'The supplier has rejected your reservation.'));
        }

        return response()->json(['message' => 'Reservation rejected.', 'reservation' => $reservation]);
    }

    public function verifyPin(Request $request, int $id)
    {
        $request->validate(['pin' => 'required|string|size:6']);
        $reservation = Reservations_materielles::find($id);
        if (!$reservation) return response()->json(['message' => 'Reservation not found.'], 404);
        if ($reservation->fournisseur_id !== Auth::id()) return response()->json(['message' => 'Unauthorized.'], 403);

        // Allow PIN verification for confirmed or paid reservations (no payment system yet)
        if (!in_array($reservation->status, ['confirmed', 'paid'])) {
            return response()->json(['message' => 'PIN can only be verified for confirmed reservations.'], 400);
        }

        if (!$reservation->verifyPin($request->pin)) {
            return response()->json(['message' => 'Incorrect or already used PIN.'], 422);
        }

        return response()->json([
            'message'          => 'PIN verified. Equipment handoff confirmed.',
            'reservation'      => $reservation->fresh(),
            'ready_for_payout' => $reservation->isReadyForPayout(),
        ]);
    }

    public function markReturned(int $id)
    {
        $reservation = Reservations_materielles::find($id);
        if (!$reservation) return response()->json(['message' => 'Reservation not found.'], 404);
        if ($reservation->fournisseur_id !== Auth::id()) return response()->json(['message' => 'Unauthorized.'], 403);
        if ($reservation->type_reservation !== 'location') return response()->json(['message' => 'This action is only for rentals.'], 400);
        if ($reservation->status !== 'retrieved') return response()->json(['message' => 'Equipment must be retrieved before marking as returned.'], 400);

        DB::beginTransaction();
        try {
            $reservation->status      = 'returned';
            $reservation->returned_at = now();
            $reservation->save();
            $reservation->materielle->increment('quantite_dispo', $reservation->quantite);
            DB::commit();

            // Notify camper that their equipment return has been confirmed
            try {
                $reservation->load(['user', 'materielle', 'fournisseur']);
                if ($reservation->user) {
                    Mail::to($reservation->user->email)->send(new RentalReturnConfirmed($reservation));
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send return confirmation email: ' . $e->getMessage());
            }

            return response()->json([
                'message'          => 'Return confirmed.',
                'reservation'      => $reservation,
                'ready_for_payout' => true,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function cancelByFournisseur(int $id)
    {
        $reservation = Reservations_materielles::with('materielle')->find($id);
        if (!$reservation) return response()->json(['message' => 'Reservation not found.'], 404);
        if ($reservation->fournisseur_id !== Auth::id()) return response()->json(['message' => 'Unauthorized.'], 403);
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'This reservation can no longer be canceled.'], 400);
        }

        DB::beginTransaction();
        try {
            if ($reservation->status === 'confirmed') {
                $reservation->materielle->increment('quantite_dispo', $reservation->quantite);
            }
            $reservation->update(['status' => 'cancelled_by_fournisseur']);
            DB::commit();
            if ($reservation->user) {
                Mail::to($reservation->user->email)
                    ->send(new ReservationCanceledByFournisseurToCamper($reservation));
            }
            return response()->json(['message' => 'Reservation canceled.', 'reservation' => $reservation]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // CAMPER ACTIONS
    // -------------------------------------------------------------------------

    public function destroy(int $id)
    {
        $reservation = Reservations_materielles::with('materielle')->find($id);
        if (!$reservation) return response()->json(['message' => 'Reservation not found.'], 404);
        if ($reservation->user_id !== Auth::id()) return response()->json(['message' => 'Unauthorized.'], 403);
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'This reservation can no longer be canceled.'], 400);
        }

        DB::beginTransaction();
        try {
            if ($reservation->status === 'confirmed') {
                $reservation->materielle->increment('quantite_dispo', $reservation->quantite);
            }
            $reservation->update(['status' => 'cancelled_by_camper']);

            DB::commit();

            $fournisseur = User::find($reservation->fournisseur_id);
            if ($fournisseur) {
                Mail::to($fournisseur->email)->send(new ReservationCanceledToFournisseur($reservation));
            }

            return response()->json(['message' => 'Reservation canceled successfully.', 'reservation' => $reservation]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // SCHEDULED / ADMIN
    // -------------------------------------------------------------------------

    public function restockExpiredReservations()
    {
        $overdue = Reservations_materielles::where('type_reservation', 'location')
            ->where('status', 'retrieved')
            ->where('date_fin', '<', now())
            ->whereNull('returned_at')
            ->get();

        if ($overdue->isEmpty()) return response()->json(['message' => 'No expired reservations to process.']);

        DB::beginTransaction();
        try {
            foreach ($overdue as $reservation) {
                $reservation->update(['status' => 'disputed']);
            }
            DB::commit();

            // Notify both parties for every disputed reservation
            foreach ($overdue as $reservation) {
                try {
                    $reservation->load(['user', 'materielle', 'fournisseur']);
                    if ($reservation->user) {
                        Mail::to($reservation->user->email)->send(new ReservationDisputedToCamper($reservation));
                    }
                    if ($reservation->fournisseur) {
                        Mail::to($reservation->fournisseur->email)->send(new ReservationDisputedToFournisseur($reservation));
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to send dispute notification for reservation ' . $reservation->id . ': ' . $e->getMessage());
                }
            }

            return response()->json(['message' => count($overdue) . ' expired reservation(s) marked as disputed.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}