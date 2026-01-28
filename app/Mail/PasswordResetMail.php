<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;
    
    public $code;
    public $resetLink;
    public $frontendUrl;
    public $user;
    public $expiresAt;
    
    public function __construct(
        string $code,
        string $resetLink,
        string $frontendUrl,
        User $user = null,
        Carbon $expiresAt = null
    ) {
        $this->code = $code;
        $this->resetLink = $resetLink;
        $this->frontendUrl = $frontendUrl;
        $this->user = $user;
        $this->expiresAt = $expiresAt ?? now()->addMinutes(30);
    }
    
    public function build()
    {
        return $this->subject('Reset Your TunisiaCamp Password')
            ->markdown('emails.password-reset')
            ->with([
                'code' => $this->code,
                'link' => $this->resetLink,
                'frontendUrl' => $this->frontendUrl,
                'user' => $this->user,
                'expiresAt' => $this->expiresAt,
                'appName' => config('app.name'),
            ]);
    }
}