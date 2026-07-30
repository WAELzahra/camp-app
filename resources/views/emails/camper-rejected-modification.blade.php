@component('mail::message')
# Modification refusée

Bonjour **{{ $reservation->centre->name ?? 'cher centre' }}**,

**{{ $camperName }}** a examiné les modifications que vous proposiez pour sa réservation et a choisi de les refuser.

La réservation est repassée au statut **En attente** — vous pouvez la consulter à nouveau et soit l'approuver telle qu'initialement soumise, soit contacter le client pour discuter d'alternatives.

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Arrivée :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }}
**Départ :** {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
**Voyageurs :** {{ $reservation->nbr_place }}
**Statut actuel :** En attente (une action de votre part est nécessaire)
@endcomponent

@component('mail::button', ['url' => $frontendUrl . '/settings/reservations', 'color' => 'primary'])
Voir mes réservations
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Réservation n° {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
