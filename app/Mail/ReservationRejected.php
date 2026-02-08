<?php

namespace App\Mail;

use App\Models\Reservations_centre;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationRejected extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $reason;
    public $reservation;
    public $frontendUrl;
    public $supportEmail;

    public function __construct($user, $reason = null, $reservation = null)
    {
        $this->user = $user;
        $this->reason = $reason;
        $this->reservation = $reservation;
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $this->supportEmail = config('mail.support_email', 'support@tunisiacamp.com');
    }

    public function build()
    {
        return $this->subject('Reservation Rejected - TunisiaCamp')
                    ->markdown('emails.reservation-rejected');
    }
}