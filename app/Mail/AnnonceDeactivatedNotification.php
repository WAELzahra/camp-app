<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnnonceDeactivatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $annonce;

    public function __construct(User $user, $annonce)
    {
        $this->user = $user;
        $this->annonce = $annonce;
    }

    public function build()
    {
        return $this->subject('Votre annonce a été désactivée')
            ->view('emails.annonce_deactivated');
    }
}
