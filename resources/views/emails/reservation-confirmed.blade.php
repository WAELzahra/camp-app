@component('mail::message')
# Réservation confirmée !

Bonjour {{ $reservation->user->first_name ?? 'cher client' }},

Votre réservation pour l'événement **{{ $reservation->event->title ?? $reservation->event->description }}** a été confirmée.

**Nombre de places :** {{ $reservation->nbr_place }}

Merci pour votre confiance.

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
