<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Reservations_centre;

class NewReservationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $centre;
    public $user;
    public $reservation;

    public function __construct(User $centre, User $user, Reservations_centre $reservation)
    {
        $this->centre = $centre;
        $this->user = $user;
        $this->reservation = $reservation;
    }

    public function build()
    {
        return $this->subject('Nouvelle réservation reçue')
                    ->view('emails.new_reservation');
    }
}
