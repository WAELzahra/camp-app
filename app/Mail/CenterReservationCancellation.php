<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CenterReservationCancellation extends Mailable
{
    use Queueable, SerializesModels;

    public $center;
    public $reservation;

    public function __construct($center, $reservation)
    {
        $this->center = $center;
        $this->reservation = $reservation;
    }

    public function build()
    {
        return $this->subject('Reservation Cancellation Record - TunisiaCamp')
                    ->markdown('emails.center_cancellation_confirmation')
                    ->with([
                        'centerName' => $this->center->name ?? $this->center->first_name . ' ' . $this->center->last_name,
                        'reservationId' => $this->reservation->id,
                        'userName' => $this->reservation->user->first_name . ' ' . $this->reservation->user->last_name,
                        'userEmail' => $this->reservation->user->email,
                        'startDate' => $this->reservation->date_debut,
                        'endDate' => $this->reservation->date_fin,
                        'totalPrice' => $this->reservation->total_price,
                        'canceledAt' => $this->reservation->canceled_at->format('F j, Y \a\t g:i A'),
                        'vacantSpots' => $this->reservation->nbr_place,
                        'cancellationReason' => 'Center initiated cancellation',
                    ]);
    }
}