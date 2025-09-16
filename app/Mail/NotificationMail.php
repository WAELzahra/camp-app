<?php

namespace App\Mail;

use App\Models\Messages;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $messageData;
    public $user;

    public function __construct(Messages $message, $user)
    {
        $this->messageData = $message;
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject("Nouvelle notification: {$this->messageData->type}")
                    ->view('emails.notification')
                    ->with([
                        'contenu' => $this->messageData->contenu,
                        'urgence' => $this->messageData->degree_urgence,
                        'user'    => $this->user,
                    ]);
    }
}
