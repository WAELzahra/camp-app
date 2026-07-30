<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $verificationCode;

    public $frontendUrl;

    public $user;

    public $expiresAt;

    public function __construct(
        string $verificationCode,
        string $frontendUrl,
        ?User $user = null,
        ?Carbon $expiresAt = null
    ) {
        $this->verificationCode = $verificationCode;
        $this->frontendUrl = $frontendUrl;
        $this->user = $user;
        $this->expiresAt = $expiresAt ?? now()->addMinutes(15);
    }

    public function build()
    {
        return $this->subject('Bienvenue sur TunisiaCamp ! Vérifiez votre adresse e-mail')
            ->markdown('emails.verification')
            ->with([
                'code' => $this->verificationCode,
                'frontendUrl' => $this->frontendUrl,
                'user' => $this->user,
                'expiresAt' => $this->expiresAt,
                'appName' => config('app.name'),
                'supportEmail' => config('mail.support_email'),
            ]);
    }
}
