<?php

namespace App\Mail;

use App\Models\SupplierInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupplierInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $invitation;

    public $organizer;

    public $organizerMessage;

    public $frontendUrl;

    public function __construct(SupplierInvitation $invitation, User $organizer, ?string $organizerMessage = null)
    {
        $this->invitation = $invitation;
        $this->organizer = $organizer;
        $this->organizerMessage = $organizerMessage;
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    }

    public function build()
    {
        $organizerName = trim(
            ($this->organizer->first_name ?? '').' '.
            ($this->organizer->last_name ?? '')
        ) ?: ($this->organizer->email ?? 'An organizer');

        return $this->subject("You're invited to join TunisiaCamp as a Supplier — by {$organizerName}")
            ->markdown('emails.supplier-invitation');
    }
}
