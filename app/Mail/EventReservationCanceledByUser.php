<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventReservationCanceledByUser extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $event,
        public $reservation,
        public $refundAmount,
        public $cancellationFee,
        public $processingFee,
    ) {}

    public function build()
    {
        return $this->subject('Your Event Reservation Has Been Canceled - TunisiaCamp')
                    ->markdown('emails.event_canceled_by_user')
                    ->with([
                        'userName'         => $this->user->first_name . ' ' . $this->user->last_name,
                        'userEmail'        => $this->user->email,
                        'eventTitle'       => $this->event->title,
                        'eventStartDate'   => $this->event->start_date,
                        'eventEndDate'     => $this->event->end_date,
                        'reservationId'    => $this->reservation->id,
                        'totalPrice'       => $this->reservation->nbr_place * $this->event->price,
                        'nbrPlace'         => $this->reservation->nbr_place,
                        'refundAmount'     => $this->refundAmount,
                        'cancellationFee'  => $this->cancellationFee,
                        'processingFee'    => $this->processingFee,
                        'canceledAt'       => now()->format('F j, Y \a\t g:i A'),
                        'cancellationFeePercent'  => config('app.cancellation_fee_percent', 15),
                        'processingFeePercent'    => config('app.processing_fee_percent', 2),
                    ]);
    }
}