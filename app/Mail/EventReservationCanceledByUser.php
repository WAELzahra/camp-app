<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventReservationCanceledByUser extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $event,
        public $reservation,
        /** Actual amount refunded to the camper's wallet, or null if nothing was refunded. */
        public $refundAmount,
        /** Platform cancellation fee actually deducted (0 if none applied). */
        public $platformFee = 0.0,
        /** Human-readable description of the cancellation-policy tier applied, or null. */
        public $feeLabel = null,
    ) {}

    public function build()
    {
        return $this->subject('Votre réservation d\'événement a été annulée — TunisiaCamp')
            ->markdown('emails.event_canceled_by_user')
            ->with([
                'userName' => $this->user->first_name.' '.$this->user->last_name,
                'userEmail' => $this->user->email,
                'eventTitle' => $this->event->title,
                'eventStartDate' => $this->event->start_date,
                'eventEndDate' => $this->event->end_date,
                'reservationId' => $this->reservation->id,
                'totalPrice' => $this->reservation->nbr_place * $this->event->price,
                'nbrPlace' => $this->reservation->nbr_place,
                'refundAmount' => $this->refundAmount,
                'platformFee' => $this->platformFee,
                'feeLabel' => $this->feeLabel,
                'paymentMethod' => $this->reservation->payment_method,
                'canceledAt' => now()->locale('fr')->translatedFormat('d F Y \à H:i'),
                'supportEmail' => config('mail.support_email'),
            ]);
    }
}
