<?php

namespace App\Mail;

use App\Models\Reservations_centre;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CamperRejectedModification extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $frontendUrl;

    public function __construct(Reservations_centre $reservation)
    {
        $this->reservation  = $reservation;
        $this->frontendUrl  = config('app.frontend_url', 'http://localhost:5173');
    }

    public function build()
    {
        $camperName = trim(($this->reservation->user->first_name ?? '') . ' ' . ($this->reservation->user->last_name ?? '')) ?: 'The camper';

        return $this->subject('Reservation Modification Declined — TunisiaCamp')
            ->markdown('emails.camper-rejected-modification')
            ->with([
                'reservation' => $this->reservation,
                'camperName'  => $camperName,
                'frontendUrl' => $this->frontendUrl,
            ]);
    }
}
