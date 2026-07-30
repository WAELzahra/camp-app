@component('mail::message')
# Réservation reçue

Bonjour **{{ $reservation->name ?? 'cher participant' }}**,

@if($reservation->payment_method === 'wallet')
Votre réservation pour l'événement **{{ $event->title ?? 'l\'événement' }}** est **confirmée** — le paiement a été effectué via votre wallet TunisiaCamp.
@elseif($reservation->payment_method === 'cash')
Votre réservation pour l'événement **{{ $event->title ?? 'l\'événement' }}** a bien été enregistrée. Le paiement se fera en espèces à l'arrivée, une fois votre réservation validée par l'organisateur.
@else
Votre réservation pour l'événement **{{ $event->title ?? 'l\'événement' }}** a bien été enregistrée. Complétez votre paiement pour confirmer votre place.
@endif

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Événement :** {{ $event->title ?? 'N/A' }}
**Date :** {{ \Carbon\Carbon::parse($event->start_date ?? $event->date_debut ?? now())->locale('fr')->translatedFormat('d/m/Y') }}
**Places réservées :** {{ $reservation->nbr_place }}
**Prix total :** {{ number_format(($reservation->nbr_place ?? 1) * ($event->price ?? 0), 2) }} TND
**Statut :** {{ $reservation->payment_method === 'wallet' ? 'Confirmée' : ($reservation->payment_method === 'cash' ? 'En attente de validation par l\'organisateur' : 'En attente de paiement') }}
@endcomponent

@if($reservation->payment_method === 'manual')
Merci de compléter votre paiement pour sécuriser votre participation. Votre place est réservée jusqu'à confirmation du paiement.

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
Compléter le paiement
@endcomponent
@else
@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
Voir ma réservation
@endcomponent
@endif

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Réservation n° {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
