<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;
    
    public $verificationCode;
    public $user;
    
    public function __construct($verificationCode, User $user = null)
    {
        $this->verificationCode = $verificationCode;
        $this->user = $user;
    }
    
    public function build()
    {
        return $this->subject('Welcome to CampConnect! Verify Your Email')
            ->markdown('emails.verification-code')
            ->with([
                'code' => $this->verificationCode,
                'user' => $this->user,
            ]);
    }
}