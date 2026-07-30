<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationCanceledByCenter extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $center;

    public $reservation;

    public function __construct($user, $center, $reservation)
    {
        $this->user = $user;
        $this->center = $center;
        $this->reservation = $reservation;
    }

    public function build()
    {
        return $this->subject('Votre réservation a été annulée — TunisiaCamp')
            ->markdown('emails.canceled_by_center')
            ->with([
                'userName' => $this->user->first_name.' '.$this->user->last_name,
                'centerName' => $this->center->name ?? $this->center->first_name.' '.$this->center->last_name,
                'centerEmail' => $this->center->email,
                'centerPhone' => $this->center->phone_number ?? $this->center->contact_phone ?? 'Non renseigné',
                'reservationId' => $this->reservation->id,
                'startDate' => $this->reservation->date_debut,
                'endDate' => $this->reservation->date_fin,
                'totalPrice' => $this->reservation->total_price,
                'canceledAt' => $this->reservation->canceled_at->locale('fr')->translatedFormat('d F Y \à H:i'),
                'supportEmail' => config('mail.support_email'),
            ]);
    }
}
