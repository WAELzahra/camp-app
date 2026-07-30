<?php

namespace App\Mail;

use App\Models\Events;
use App\Models\Reservations_events;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventReservationCanceledByGroup extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $reservation;

    public $event;

    public $frontendUrl;

    /** Actual amount refunded to the camper's wallet, or null if nothing was ever charged. */
    public $refundAmount;

    public $supportEmail;

    public function __construct(Reservations_events $reservation, Events $event, $refundAmount = null)
    {
        $this->reservation = $reservation;
        $this->event = $event;
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $this->refundAmount = $refundAmount;
        $this->supportEmail = config('mail.support_email');
    }

    public function build()
    {
        return $this->subject('Votre réservation d\'événement a été annulée — TunisiaCamp')
            ->markdown('emails.event-reservation-canceled-by-group');
    }
}
