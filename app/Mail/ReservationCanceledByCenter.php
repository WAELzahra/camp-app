<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationCanceledByCenter extends Mailable
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
        return $this->subject('Your Reservation Has Been Canceled - TunisiaCamp')
                    ->markdown('emails.canceled_by_center')
                    ->with([
                        'userName' => $this->user->first_name . ' ' . $this->user->last_name,
                        'centerName' => $this->center->name ?? $this->center->first_name . ' ' . $this->center->last_name,
                        'centerEmail' => $this->center->email,
                        'centerPhone' => $this->center->phone_number ?? $this->center->contact_phone ?? 'Not provided',
                        'reservationId' => $this->reservation->id,
                        'startDate' => $this->reservation->date_debut,
                        'endDate' => $this->reservation->date_fin,
                        'totalPrice' => $this->reservation->total_price,
                        'refundPolicy' => 'Please contact the center directly for refund information.',
                        'canceledAt' => $this->reservation->canceled_at->format('F j, Y \a\t g:i A'),
                        'alternativeCenters' => $this->getAlternativeCenters(),
                    ]);
    }

    private function getAlternativeCenters()
    {
        // You could query for alternative centers here
        return [
            [
                'name' => 'Tunisia Camp Center',
                'location' => 'Tunis',
                'price_range' => '$20-40/night'
            ],
            [
                'name' => 'Sousse Beach Camp',
                'location' => 'Sousse',
                'price_range' => '$25-45/night'
            ]
        ];
    }
}