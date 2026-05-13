<?php

namespace App\Mail;

use App\Models\Reservations_materielles;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationDisputedToFournisseur extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $frontendUrl;
    public $supportEmail;

    public function __construct(Reservations_materielles $reservation)
    {
        $this->reservation  = $reservation;
        $this->frontendUrl  = config('app.frontend_url', 'http://localhost:5173');
        $this->supportEmail = config('mail.support_email', 'nejikh57@gmail.com');
    }

    public function build()
    {
        return $this->subject('Overdue Rental Alert - TunisiaCamp')
                    ->markdown('emails.reservation-disputed-to-fournisseur');
    }
}
