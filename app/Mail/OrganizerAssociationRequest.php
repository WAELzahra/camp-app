<?php

namespace App\Mail;

use App\Models\OrganizerSupplierLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrganizerAssociationRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $link;
    public $frontendUrl;

    public function __construct(OrganizerSupplierLink $link)
    {
        $this->link        = $link->load(['organizer', 'supplier']);
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
    }

    public function build()
    {
        $organizerName = trim(
            ($this->link->organizer->first_name ?? '') . ' ' .
            ($this->link->organizer->last_name  ?? '')
        ) ?: ($this->link->organizer->email ?? 'An organizer');

        return $this->subject("Association request from {$organizerName} — TunisiaCamp")
                    ->markdown('emails.organizer-association-request');
    }
}
