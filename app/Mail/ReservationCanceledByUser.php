<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationCanceledByUser extends Mailable
{
    use Queueable, SerializesModels;

    public $center;
    public $user;

    public function __construct($center, $user)
    {
        $this->center = $center;
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Annulation de réservation par un utilisateur')
                    ->view('emails.reservation_canceled_by_user');
    }
}
