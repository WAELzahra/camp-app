<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;
    
    public $verificationCode;
    public $verificationLink;
    public $frontendUrl;
    public $user;
    public $expiresAt;
    
    public function __construct(
        string $verificationCode,
        string $verificationLink,
        string $frontendUrl,
        User $user = null,
        Carbon $expiresAt = null
    ) {
        $this->verificationCode = $verificationCode;
        $this->verificationLink = $verificationLink;
        $this->frontendUrl = $frontendUrl;
        $this->user = $user;
        $this->expiresAt = $expiresAt ?? now()->addMinutes(15);
    }
    
    public function build()
    {
        return $this->subject('Welcome to CampConnect! Verify Your Email Address')
            ->markdown('emails.verification')
            ->with([
                'code' => $this->verificationCode,
                'link' => $this->verificationLink,
                'frontendUrl' => $this->frontendUrl,
                'user' => $this->user,
                'expiresAt' => $this->expiresAt,
                'appName' => config('app.name'),
                'supportEmail' => config('mail.support_email', 'support@campconnect.tn'),
            ]);
    }
}