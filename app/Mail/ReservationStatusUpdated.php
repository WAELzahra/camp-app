<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationStatusUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $reservation;

    public $type;

    public $oldStatus;

    public $recipientName;

    public $itemName;

    public function __construct($reservation, string $type, string $oldStatus = '')
    {
        $this->reservation = $reservation;
        $this->type = $type;
        $this->oldStatus = $oldStatus;

        // Resolve a human-readable recipient name (works for all types)
        $user = $reservation->user ?? null;
        if ($user) {
            $this->recipientName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email;
        } else {
            $this->recipientName = $reservation->name ?? 'Customer';
        }

        // Resolve a human-readable item/entity name
        $this->itemName = match ($type) {
            'center' => $reservation->centre->first_name ?? "Centre #{$reservation->id}",
            'events' => $reservation->event->title ?? "Événement #{$reservation->id}",
            'materielle' => $reservation->materielle->nom ?? "Matériel #{$reservation->id}",
            'guides' => $reservation->circuit->name ?? "Circuit #{$reservation->id}",
            default => "Réservation #{$reservation->id}",
        };
    }

    public function build()
    {
        return $this->subject('Le statut de votre réservation a été mis à jour')
            ->markdown('emails.reservation_status_updated');
    }
}
