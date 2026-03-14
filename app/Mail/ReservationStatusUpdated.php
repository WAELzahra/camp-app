<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationRejectedToUser extends Mailable
{
    use Queueable, SerializesModels;

    public $camper;
    public $reason;

    public function __construct($camper, string $reason = '')
    {
        $this->camper = $camper;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('Your Reservation Request Has Been Rejected')
                    ->markdown('emails.rejected_to_user');
    }
}