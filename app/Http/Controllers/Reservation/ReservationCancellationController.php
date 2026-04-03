<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Models\Reservations_events;
use App\Models\RefundRequest;
use App\Models\Payment;
use App\Models\Events;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventReservationCanceledByUser;
use App\Mail\EventReservationCanceledNotifyOwner;

class ReservationCancellationController extends Controller
{
   public function annulerReservation($reservationId)
{
    $reservation = Reservations_events::with('payment')->findOrFail($reservationId);

    // 🔐 Vérification utilisateur
    if (auth()->id() !== $reservation->user_id) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    // ✅ Ne pas annuler plusieurs fois
    if (in_array($reservation->status, ['annulée_par_utilisateur', 'remboursée_totale'])) {
        return response()->json(['message' => 'Réservation déjà annulée ou remboursée.'], 400);
    }

    DB::beginTransaction();

    try {
        $reservation->update(['status' => 'annulée_par_utilisateur']);

        $payment = $reservation->payment;

        // 🔎 Si aucun paiement confirmé, pas de remboursement
        if (!$payment || $payment->status !== 'confirmed') {
            return response()->json(['message' => 'Aucun paiement confirmé.'], 404);
        }

        // 💰 Calcul du remboursement partiel
        $montantRembourse = $payment->montant / 2;

        // 🔁 Création de la demande de remboursement
        RefundRequest::create([
            'reservation_event_id' => $reservation->id,
            'payment_id' => $payment->id,
            'montant_rembourse' => $montantRembourse,
            'status' => 'en_attente',
        ]);

        DB::commit();

        // Notify the user and event owner
        try {
            $event = Events::find($reservation->event_id);
            $user  = User::find($reservation->user_id);
            if ($event && $user) {
                $totalPrice      = $reservation->nbr_place * ($event->price ?? 0);
                $cancellationFee = $totalPrice - $montantRembourse;

                Mail::to($user->email)->send(new EventReservationCanceledByUser(
                    user:            $user,
                    event:           $event,
                    reservation:     $reservation,
                    refundAmount:    $montantRembourse,
                    cancellationFee: $cancellationFee,
                    processingFee:   0,
                ));

                $owner = User::find($event->group_id);
                if ($owner) {
                    Mail::to($owner->email)->send(new EventReservationCanceledNotifyOwner(
                        owner:       $owner,
                        user:        $user,
                        event:       $event,
                        reservation: $reservation,
                    ));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send cancellation emails in annulerReservation: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Réservation annulée. Demande de remboursement générée.',
            'montant_rembourse' => $montantRembourse,
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
    }
}

}
