<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewReservationToFournisseur extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $camper;

    public function __construct($reservation, $camper)
    {
        $this->reservation = $reservation;
        $this->camper      = $camper;
    }

    public function build()
    {
        return $this->subject('New Reservation Request Received')
                    ->markdown('emails.new_to_fournisseur');
    }
}