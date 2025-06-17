<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationRejected extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $reason;

    public function __construct($user, $reason = null)
    {
        $this->user = $user;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('Votre réservation a été rejetée')
                    ->view('emails.reservation_rejected');
    }
}
