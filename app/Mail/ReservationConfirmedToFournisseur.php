<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class ReservationConfirmedToFournisseur extends Mailable implements ShouldQueue
{
    public $reservation;

    public function __construct($reservation)
    {
        $this->reservation = $reservation;
    }

    public function build()
    {
        return $this->subject('Réservation confirmée')
            ->markdown('emails.reservation_materielle_confirmed');
    }
}
