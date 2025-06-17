<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class AnnonceDeactivatedNotification extends Mailable
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
