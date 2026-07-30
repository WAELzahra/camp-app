<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewReservationToFournisseur extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $reservation;

    public $camper;

    public function __construct($reservation, $camper)
    {
        $this->reservation = $reservation;
        $this->camper = $camper;
    }

    public function build()
    {
        return $this->subject('Nouvelle demande de réservation reçue')
            ->markdown('emails.new_to_fournisseur');
    }
}
