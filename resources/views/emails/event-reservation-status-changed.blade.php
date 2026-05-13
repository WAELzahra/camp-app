@component('mail::message')
@php
$statusLabels = [
    'confirmé'               => ['label' => 'Confirmed ✅',  'color' => 'success'],
    'refusé'                 => ['label' => 'Rejected ❌',   'color' => 'error'],
    'en_attente'             => ['label' => 'Pending ⏳',    'color' => 'primary'],
    'annulé'                 => ['label' => 'Cancelled ⚠️', 'color' => 'error'],
    'en_attente_paiement'    => ['label' => 'Awaiting Payment 💳', 'color' => 'primary'],
    'annulée_par_utilisateur'=> ['label' => 'Cancelled by You', 'color' => 'error'],
];
$info = $statusLabels[$reservation->status] ?? ['label' => ucfirst($reservation->status), 'color' => 'primary'];
@endphp

# Reservation Status Updated

Hi **{{ $reservation->name ?? 'Participant' }}**,

The status of your reservation for **{{ $event->title ?? 'the event' }}** has been updated.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Event:** {{ $event->title ?? 'N/A' }}
**Event Date:** {{ \Carbon\Carbon::parse($event->start_date ?? $event->date_debut ?? now())->format('d/m/Y') }}
**Spots:** {{ $reservation->nbr_place }}
**New Status:** {{ $info['label'] }}
@endcomponent

@if($reservation->status === 'confirmé')
Your spot is confirmed! We look forward to seeing you at the event.
@elseif($reservation->status === 'refusé')
Unfortunately your reservation was not accepted. You may browse other events on TunisiaCamp.
@elseif($reservation->status === 'en_attente_paiement')
Please complete your payment to secure your spot.
@endif

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
View My Reservations
@endcomponent

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Reservation ID: {{ $reservation->id }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent
