<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserReservationCancellation extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $reservation;

    public function __construct($user, $reservation)
    {
        $this->user = $user;
        $this->reservation = $reservation;
    }

    public function build()
    {
        return $this->subject('Reservation Cancellation Confirmation - TunisiaCamp')
                    ->markdown('emails.user_cancellation_confirmation')
                    ->with([
                        'userName' => $this->user->first_name . ' ' . $this->user->last_name,
                        'reservationId' => $this->reservation->id,
                        'centerName' => $this->reservation->centre->name ?? 'Unknown Center',
                        'startDate' => $this->reservation->date_debut,
                        'endDate' => $this->reservation->date_fin,
                        'totalPrice' => $this->reservation->total_price,
                        'canceledAt' => $this->reservation->canceled_at->format('F j, Y \a\t g:i A'),
                        'cancellationNumber' => 'CAN-' . str_pad($this->reservation->id, 6, '0', STR_PAD_LEFT),
                    ]);
    }
}