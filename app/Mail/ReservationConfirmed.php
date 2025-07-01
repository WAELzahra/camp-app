<?php

namespace App\Mail;

<<<<<<< HEAD
=======
use App\Models\Reservations_envets;
>>>>>>> origin/sprint-3
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

<<<<<<< HEAD
    public $user;

    public function __construct($user)
    {
        $this->user = $user;
=======
    public $reservation;

    public function __construct(Reservations_envets $reservation)
    {
        $this->reservation = $reservation;
>>>>>>> origin/sprint-3
    }

    public function build()
    {
<<<<<<< HEAD
        return $this->subject('Votre réservation a été confirmée')
                    ->view('emails.reservation_confirmed');
    }
}
=======
        return $this->subject('Confirmation de votre réservation')
                    ->view('emails.reservation-confirmed');
    }
}

>>>>>>> origin/sprint-3
