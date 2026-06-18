<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class ReservationRejectedToUser extends Mailable implements ShouldQueue
{
    public $user;

    public $motif;

    public function __construct($user, $motif)
    {
        $this->user = $user;
        $this->motif = $motif;
    }

    public function build()
    {
        return $this->subject('🚫 Réservation rejetée')
            ->view('emails.reservation_materielles_rejected');
    }
}
