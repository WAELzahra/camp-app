<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewReservationToFournisseur extends Mailable
{
    public $reservation;
    public $user;
    
    public function __construct($reservation, $user)
    {
        $this->reservation = $reservation;
        $this->user = $user;
    }
    
    public function build()
    {
        return $this->subject('📩 Nouvelle réservation reçue')
                    ->view('emails.new_reservation_materielle');
    }
}
