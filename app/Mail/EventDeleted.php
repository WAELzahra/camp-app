<?php

namespace App\Mail;

use App\Models\Events;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventDeleted extends Mailable
{
    use Queueable, SerializesModels;

    public $event;

    public function __construct(Events $event)
    {
        $this->event = $event;
    }

    public function build()
    {
        return $this->subject('Votre événement a été supprimé')
                    ->view('emails.event_deleted');
    }
}

