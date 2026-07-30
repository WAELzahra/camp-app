@component('mail::message')
# Nouvelle réservation

Bonjour {{ $centre->first_name ?? 'cher centre' }},

Vous avez reçu une nouvelle réservation de la part de **{{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email }}**.

@component('mail::panel')
**Date de début :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }}
**Date de fin :** {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
**Type :** {{ $reservation->type }}
**Nombre de places :** {{ $reservation->nbr_place }}
@if($reservation->note)
**Note :** {{ $reservation->note }}
@endif
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
