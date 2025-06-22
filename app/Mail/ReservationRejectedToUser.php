<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationRejectedToUser extends Mailable
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
