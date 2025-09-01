<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Materielles;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MaterielleNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $materielle;
    public $type;

    public function __construct(User $user, Materielles $materielle, string $type)
    {
        $this->user = $user;
        $this->materielle = $materielle;
        $this->type = $type;
    }

    public function build()
    {
        return $this->subject("Notification: {$this->type}")
                    ->view('emails.materielle_notification')
                    ->with([
                        'user' => $this->user,
                        'materielle' => $this->materielle,
                        'type' => $this->type,
                    ]);
    }
}