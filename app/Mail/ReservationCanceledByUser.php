<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationCanceledByUser extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $center;

    public $user;

    public $reservation;

    public function __construct($center, $user, $reservation)
    {
        $this->center = $center;
        $this->user = $user;
        $this->reservation = $reservation;
    }

    public function build()
    {
        return $this->subject('Reservation Canceled by User - TunisiaCamp')
            ->markdown('emails.canceled_by_user')
            ->with([
                'centerName' => $this->center->name ?? $this->center->first_name.' '.$this->center->last_name,
                'userName' => $this->user->first_name.' '.$this->user->last_name,
                'userEmail' => $this->user->email,
                'reservationId' => $this->reservation->id,
                'startDate' => $this->reservation->date_debut,
                'endDate' => $this->reservation->date_fin,
                'totalPrice' => $this->reservation->total_price,
                'note' => $this->reservation->note,
                'canceledAt' => $this->reservation->canceled_at->format('F j, Y \a\t g:i A'),
                'serviceCount' => $this->reservation->service_count ?? 0,
            ]);
    }
}
