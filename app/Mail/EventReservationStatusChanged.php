<?php

namespace App\Mail;

use App\Models\Reservations_events;
use App\Models\Events;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventReservationStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $event;
    public $frontendUrl;

    public function __construct(Reservations_events $reservation, Events $event)
    {
        $this->reservation = $reservation;
        $this->event       = $event;
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    }

    public function build()
    {
        return $this->subject('Your Event Reservation Status Updated - TunisiaCamp')
                    ->markdown('emails.event-reservation-status-changed');
    }
}
