<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $isActive;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, $isActive)
    {
        $this->user = $user;
        $this->isActive = $isActive;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $statusText = $this->isActive == 1 ? 'activé' : 'désactivé';
        
        return $this->subject('Mise à jour du statut de votre compte - ' . config('app.name'))
                    ->view('emails.account_status_changed')
                    ->with([
                        'userName' => $this->user->first_name . ' ' . $this->user->last_name,
                        'userEmail' => $this->user->email,
                        'status' => $statusText,
                        'statusValue' => $this->isActive,
                        'appName' => config('app.name'),
                        'loginUrl' => url('/login'),
                        'supportEmail' => 'support@' . config('app.domain', 'example.com'),
                        'currentYear' => date('Y')
                    ]);
    }
}