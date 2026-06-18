<?php

namespace App\Mail;

use App\Models\Reservations_centre;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewReservationNotification extends Mailable implements ShouldQueue
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
