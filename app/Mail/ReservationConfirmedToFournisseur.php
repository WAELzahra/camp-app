<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmedToFournisseur extends Mailable
{
    public $reservation;

    public function __construct($reservation)
    {
        $this->reservation = $reservation;
    }
    
    public function build()
    {
        return $this->subject('✅ Réservation confirmée')
                    ->view('emails.reservation_materielle_confirmed');
    }
    
}
