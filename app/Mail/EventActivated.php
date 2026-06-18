<?php

namespace App\Mail;

use App\Models\Events;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventActivated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $event;

    public function __construct(Events $event)
    {
        $this->event = $event;
    }

    public function build()
    {
        return $this->subject('Votre événement a été validé !')
            ->view('emails.event_activated');
    }
}
