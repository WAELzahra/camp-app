<?php

namespace App\Mail;

use App\Models\Reservations_centre;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CentreReservationConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $reservation;

    public $frontendUrl;

    public $supportEmail;

    public $reservationDetailsUrl;

    public $checkInTime;

    public $checkOutTime;

    public function __construct(Reservations_centre $reservation)
    {
        $this->reservation = $reservation;
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $this->supportEmail = config('mail.support_email');
        $this->reservationDetailsUrl = $this->frontendUrl.'/reservations/'.$reservation->id;
        $this->checkInTime = '14:00';
        $this->checkOutTime = '11:00';
    }

    public function build()
    {
        return $this->subject('Réservation confirmée — TunisiaCamp')
            ->markdown('emails.centre-reservation-confirmed')
            ->with([
                'reservation' => $this->reservation,
                'frontendUrl' => $this->frontendUrl,
                'supportEmail' => $this->supportEmail,
                'reservationDetailsUrl' => $this->reservationDetailsUrl,
                'checkInTime' => $this->checkInTime,
                'checkOutTime' => $this->checkOutTime,
            ]);
    }
}
