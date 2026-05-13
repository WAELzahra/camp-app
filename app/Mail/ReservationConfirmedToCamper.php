<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmedToCamper extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $pin;

    public function __construct($reservation, string $pin)
    {
        $this->reservation = $reservation;
        $this->pin         = $pin;
    }

    public function build()
    {
        return $this->subject('Your Reservation is Confirmed! Here is Your PIN')
                    ->markdown('emails.confirmed_to_camper');
    }
}