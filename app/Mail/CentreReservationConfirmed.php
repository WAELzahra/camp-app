<?php

namespace App\Mail;

use App\Models\Reservations_centre;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class CentreReservationConfirmed extends Mailable
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
        $this->supportEmail = config('mail.support_email', 'nejikh57@gmail.com');
        $this->reservationDetailsUrl = $this->frontendUrl . '/reservations/' . $reservation->id;
        $this->checkInTime = '14:00';
        $this->checkOutTime = '11:00';
    }
    
    public function build()
    {
        // CHANGE THIS: Use markdown() instead of view() 
        // JUST LIKE EmailVerificationMail class
        return $this->subject('Reservation Confirmed - TunisiaCamp')
            ->markdown('emails.centre-reservation-confirmed')  // ← Changed to markdown
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