<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Events;

class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $event;

    public function __construct(Events $event)
    {
        $this->event = $event;
    }

    public function build()
    {
        return $this->subject('Rappel : Événement à venir - ' . $this->event->title)
                    ->view('emails.event_reminder')
                    ->with(['event' => $this->event]);
    }
}
