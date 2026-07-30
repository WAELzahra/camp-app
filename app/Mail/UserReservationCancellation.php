<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserReservationCancellation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $reservation;

    /** Actual amount refunded to the camper's wallet, or null if no wallet refund applied. */
    public $refundAmount;

    /** Human-readable reason for the refund figure (cancellation policy tier, or null). */
    public $refundNote;

    public function __construct($user, $reservation, $refundAmount = null, $refundNote = null)
    {
        $this->user = $user;
        $this->reservation = $reservation;
        $this->refundAmount = $refundAmount;
        $this->refundNote = $refundNote;
    }

    public function build()
    {
        return $this->subject('Confirmation d\'annulation de votre réservation — TunisiaCamp')
            ->markdown('emails.user_cancellation_confirmation')
            ->with([
                'userName' => $this->user->first_name.' '.$this->user->last_name,
                'reservationId' => $this->reservation->id,
                'centerName' => $this->reservation->centre->name ?? 'Centre inconnu',
                'startDate' => $this->reservation->date_debut,
                'endDate' => $this->reservation->date_fin,
                'totalPrice' => $this->reservation->total_price,
                'paymentMethod' => $this->reservation->payment_method,
                'canceledAt' => $this->reservation->canceled_at->locale('fr')->translatedFormat('d F Y \à H:i'),
                'cancellationNumber' => 'CAN-'.str_pad($this->reservation->id, 6, '0', STR_PAD_LEFT),
                'refundAmount' => $this->refundAmount,
                'refundNote' => $this->refundNote,
                'supportEmail' => config('mail.support_email'),
            ]);
    }
}
