<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CenterReservationCancellation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $center;

    public $reservation;

    /** Platform cancellation fee actually debited from the centre's wallet, if any. */
    public $platformFee;

    public function __construct($center, $reservation, $platformFee = 0.0)
    {
        $this->center = $center;
        $this->reservation = $reservation;
        $this->platformFee = $platformFee;
    }

    public function build()
    {
        return $this->subject('Confirmation d\'annulation de réservation — TunisiaCamp')
            ->markdown('emails.center_cancellation_confirmation')
            ->with([
                'centerName' => $this->center->name ?? $this->center->first_name.' '.$this->center->last_name,
                'reservationId' => $this->reservation->id,
                'userName' => $this->reservation->user->first_name.' '.$this->reservation->user->last_name,
                'userEmail' => $this->reservation->user->email,
                'startDate' => $this->reservation->date_debut,
                'endDate' => $this->reservation->date_fin,
                'totalPrice' => $this->reservation->total_price,
                'canceledAt' => $this->reservation->canceled_at->locale('fr')->translatedFormat('d F Y \à H:i'),
                'vacantSpots' => $this->reservation->nbr_place,
                'platformFee' => $this->platformFee,
                'supportEmail' => config('mail.support_email'),
            ]);
    }
}
