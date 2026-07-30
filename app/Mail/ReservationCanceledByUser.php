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

    /** Actual amount refunded to the camper's wallet, or null if none was due. */
    public $refundAmount;

    /** Human-readable reason for the refund figure (cancellation policy tier, or null). */
    public $refundNote;

    public function __construct($center, $user, $reservation, $refundAmount = null, $refundNote = null)
    {
        $this->center = $center;
        $this->user = $user;
        $this->reservation = $reservation;
        $this->refundAmount = $refundAmount;
        $this->refundNote = $refundNote;
    }

    public function build()
    {
        return $this->subject('Réservation annulée par le client — TunisiaCamp')
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
                'canceledAt' => $this->reservation->canceled_at->locale('fr')->translatedFormat('d F Y \à H:i'),
                'serviceCount' => $this->reservation->service_count ?? 0,
                'refundAmount' => $this->refundAmount,
                'refundNote' => $this->refundNote,
                'supportEmail' => config('mail.support_email'),
            ]);
    }
}
