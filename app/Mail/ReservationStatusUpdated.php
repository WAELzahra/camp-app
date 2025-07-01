<?php

namespace App\Mail;

use App\Models\Reservations_events;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;

    public function __construct(Reservations_events $reservation)
    {
        $this->reservation = $reservation;
    }

    public function build()
    {
        return $this->subject('Mise à jour de votre réservation')
                    ->view('emails.reservation_status_updated');
    }
}
