<?php

namespace App\Mail;

use App\Models\Reservations_materielles;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RentalReturnConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $frontendUrl;

    public function __construct(Reservations_materielles $reservation)
    {
        $this->reservation = $reservation;
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    }

    public function build()
    {
        return $this->subject('Equipment Return Confirmed - TunisiaCamp')
                    ->markdown('emails.rental-return-confirmed');
    }
}
