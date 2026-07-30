<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventReservationCanceledNotifyOwner extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public $owner,
        public $user,
        public $event,
        public $reservation,
    ) {}

    public function build()
    {
        return $this->subject('Réservation annulée par un participant — TunisiaCamp')
            ->markdown('emails.event_canceled_notify_owner')
            ->with([
                'ownerName' => $this->owner->first_name.' '.$this->owner->last_name,
                'userName' => $this->user->first_name.' '.$this->user->last_name,
                'userEmail' => $this->user->email,
                'userPhone' => $this->user->phone_number ?? 'Non renseigné',
                'eventTitle' => $this->event->title,
                'eventStartDate' => $this->event->start_date,
                'eventEndDate' => $this->event->end_date,
                'reservationId' => $this->reservation->id,
                'nbrPlace' => $this->reservation->nbr_place,
                'totalPrice' => $this->reservation->nbr_place * $this->event->price,
                'canceledAt' => now()->locale('fr')->translatedFormat('d F Y \à H:i'),
                'supportEmail' => config('mail.support_email'),
            ]);
    }
}
