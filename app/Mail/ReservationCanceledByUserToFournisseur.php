<?php

namespace App\Mail;

use App\Models\Reservations_materielles;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationCanceledByUserToFournisseur extends Mailable
{
    use Queueable, SerializesModels;

    public $fournisseur;
    public $reservation;
    public $user;

    public function __construct(User $fournisseur, User $user, Reservations_materielles $reservation)
    {
        $this->fournisseur = $fournisseur;
        $this->reservation = $reservation;
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Réservation annulée par le client')
                    ->markdown('emails.reservations.canceled_by_user');
    }
}
