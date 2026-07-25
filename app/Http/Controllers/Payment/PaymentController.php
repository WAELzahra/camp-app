<?php

namespace App\Http\Controllers\payment;

use App\Http\Controllers\Controller;
use App\Models\Reservations_events;
use PDF;

class PaymentController extends Controller
{
     /**
     * Étape 6 – Générer et afficher ticket PDF après confirmation.
     */
    public function confirmerPaiement($reservationId)
    {
        $reservation = Reservations_events::with(['user', 'event'])->findOrFail($reservationId);

        if ($reservation->status !== 'confirmée') {
            return response()->json(['message' => 'La réservation n est pas confirmée.'], 400);
        }

        $pdf = PDF::loadView('pdf.ticket', compact('reservation'));
        return $pdf->stream('ticket_' . $reservation->id . '.pdf');
    }

    // Étape 7 – Imprimer ou télécharger le ticket PDF.

    public function imprimerTicket($reservationId)
    {
        $reservation = Reservations_events::with(['user', 'event', 'payment'])->findOrFail($reservationId);
        $pdf = PDF::loadView('pdf.ticket', compact('reservation'));
        return $pdf->stream('ticket_' . $reservation->id . '.pdf');
    }

    // Étape 8 – Télécharger le ticket PDF.
    public function telechargerTicket($reservationId)
    {
        $reservation = Reservations_events::with(['user', 'event', 'payment'])->findOrFail($reservationId);
        $pdf = PDF::loadView('pdf.ticket', compact('reservation'));
        return $pdf->download('ticket_' . $reservation->id . '.pdf');
    }


}