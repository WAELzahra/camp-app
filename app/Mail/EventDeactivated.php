<?php

namespace App\Mail;

use App\Models\Events;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventDeactivated extends Mailable
{
    use Queueable, SerializesModels;

    public $event;

    public function __construct(Events $event)
    {
        $this->event = $event;
    }

    public function build()
    {
        return $this->subject('Notification de désactivation d\'événement')
                    ->view('emails.event_deactivated');
    }
}
