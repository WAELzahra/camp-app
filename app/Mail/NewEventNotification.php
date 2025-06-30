<?php
namespace App\Mail;

use App\Models\Events;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewEventNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $event;
    public $userName;

    public function __construct(Events $event, $userName)
    {
        $this->event = $event;
        $this->userName = $userName;
    }

    public function build()
    {
        return $this->subject('Nouveau événement d’un groupe que vous suivez')
            ->view('emails.new_event_notification');
    }
}
