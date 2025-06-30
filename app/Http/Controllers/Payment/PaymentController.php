<?php

namespace App\Http\Controllers\payment;

use App\Http\Controllers\Controller;
use App\Models\Payments;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Reservations_events;
use PDF;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
     public function initPayment(Request $request, $reservationId)
    {
        $reservation = Reservations_events::findOrFail($reservationId);
        $event = Events::findOrFail($reservation->event_id);

        $nbr_place = $reservation->nbr_place;
        $prix_total = $event->prix * $nbr_place;
        $montant = $request->input('montant') ?? $prix_total / 2;

        if ($montant < $prix_total * 0.5) {
            return response()->json(['error' => 'Le montant doit être au moins 50% du total.'], 422);
        }

        $commissionRate = Config::get('commission.default', 0.10);
        $commission = $montant * $commissionRate;
        $net_revenue = $montant - $commission;

        $reference = Str::uuid();

        $payment = Payments::create([
            'user_id' => auth()->id(),
            'event_id' => $event->id,
            'montant' => $montant,
            'commission' => $commission,
            'net_revenue' => $net_revenue,
            'description' => 'Paiement 50% pour l’événement ' . $event->titre,
            'status' => 'pending',
            'konnect_reference' => $reference,
        ]);

        // Associer le paiement à la réservation
        $reservation->payment_id = $payment->id;
        $reservation->save();

        $konnectResponse = Http::withToken(config('konnect.secret_key'))
            ->post('https://api.konnect.network/api/v2/checkout-hosted', [
                'order_id' => $reference,
                'amount' => $montant * 100,
                'currency' => 'TND',
                'success_url' => route('konnect.success', ['payment_id' => $payment->id]),
                'fail_url' => route('konnect.fail', ['payment_id' => $payment->id]),
                'metadata' => [
                    'user_id' => auth()->id(),
                    'reservation_id' => $reservationId,
                ]
            ]);

        if ($konnectResponse->successful()) {
            $url = $konnectResponse['redirect_url'] ?? $konnectResponse['checkout_url'];
            return response()->json(['payment_url' => $url]);
        } else {
            Log::error('Konnect init error', ['response' => $konnectResponse->body()]);
            return response()->json(['error' => 'Erreur lors de la communication avec Konnect.'], 500);
        }
    }

    /**
     * Étape 5 – Réception Webhook Konnect avec confirmation.
     */
   public function webhookKonnect(Request $request)
    {
        Log::info('🔔 Webhook Konnect reçu', $request->all());

        $data = $request->all();

        $payment = Payments::where('konnect_reference', $data['payment_reference'])->first();

        if (!$payment) {
            return response()->json(['message' => 'Paiement introuvable'], 404);
        }

        if ($data['status'] === 'completed') {
            $payment->update([
                'status' => 'confirmed',
                'konnect_payment_id' => $data['payment_reference'] ?? null
            ]);

            $reservation = Reservations_events::where('payment_id', $payment->id)->first();

            if ($reservation) {
                $reservation->update(['status' => 'confirmée']);
            }
        } else {
            $payment->update(['status' => 'failed']);
        }

        return response()->json(['message' => 'Webhook traité']);
    }
  

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