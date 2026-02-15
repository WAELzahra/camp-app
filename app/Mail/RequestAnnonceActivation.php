<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Annonce;

class RequestAnnonceActivation extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $annonce;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $annonce = null)
    {
        $this->user = $user;
        $this->annonce = $annonce;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000') . '/dashboard/annonces';
        $supportEmail = config('mail.from.address', 'support@tunisiacamp.tn');
        
        return $this->subject('Annonce en attente de validation - TunisiaCamp')
                    ->markdown('emails.annonce_request_activation')
                    ->with([
                        'frontendUrl' => $frontendUrl,
                        'supportEmail' => $supportEmail,
                        'expiresAt' => now()->addDays(7),
                    ]);
    }
}