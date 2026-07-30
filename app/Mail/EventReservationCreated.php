<?php

namespace App\Mail;

use App\Models\Events;
use App\Models\Reservations_events;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventReservationCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $reservation;

    public $event;

    public $frontendUrl;

    public function __construct(Reservations_events $reservation, Events $event)
    {
        $this->reservation = $reservation;
        $this->event = $event;
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    }

    public function build()
    {
        // This is sent for every payment method, not just manual/pending ones —
        // the subject and body must reflect what actually happened, not assume
        // a payment step is still outstanding (wallet payments are already paid).
        $subject = match ($this->reservation->payment_method) {
            'wallet' => 'Réservation confirmée — TunisiaCamp',
            'cash' => 'Réservation reçue — paiement à l\'arrivée — TunisiaCamp',
            default => 'Réservation reçue — paiement en attente — TunisiaCamp',
        };

        return $this->subject($subject)
            ->markdown('emails.event-reservation-created');
    }
}
