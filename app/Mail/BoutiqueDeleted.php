<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BoutiqueDeleted extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $boutiqueName;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $boutiqueName)
    {
        $this->user = $user;
        $this->boutiqueName = $boutiqueName;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Votre boutique a été supprimée')
                    ->view('emails.boutique_deleted');
    }
}
