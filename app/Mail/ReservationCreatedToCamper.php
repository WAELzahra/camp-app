<?php

namespace App\Mail;

use App\Models\Reservations_centre;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationCreatedToCamper extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $frontendUrl;

    public function __construct(Reservations_centre $reservation)
    {
        $this->reservation  = $reservation;
        $this->frontendUrl  = config('app.frontend_url', 'http://localhost:5173');
    }

    public function build()
    {
        return $this->subject('Reservation Request Received - TunisiaCamp')
                    ->markdown('emails.reservation-created-to-camper');
    }
}
