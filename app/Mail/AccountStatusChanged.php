<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public $isActive;

    public function __construct($isActive)
    {
        $this->isActive = $isActive;
    }

    public function build()
    {
        return $this->subject('Mise à jour du statut de votre compte')
                    ->view('emails.account_status_changed')
                    ->with([
                        'status' => $this->isActive ? 'activé' : 'désactivé',
                    ]);
    }
}
