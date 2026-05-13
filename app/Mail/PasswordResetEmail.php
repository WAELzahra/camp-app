<?php
// app/Mail/PasswordResetEmail.php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $newPassword;

    public function __construct(User $user, $newPassword)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
    }

    public function build()
    {
        return $this->subject('Réinitialisation de votre mot de passe')
                    ->view('emails.password-resets')
                    ->with([
                        'userName' => $this->user->first_name . ' ' . $this->user->last_name,
                        'newPassword' => $this->newPassword,
                        'loginUrl' => url('/login')
                    ]);
    }
}