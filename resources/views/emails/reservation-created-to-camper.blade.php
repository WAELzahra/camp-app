@component('mail::message')
# Demande de réservation reçue

Bonjour **{{ $reservation->user->first_name ?? 'cher client' }}**,

Votre demande de réservation a bien été soumise. Le centre de camping va l'examiner et vous confirmer sous peu.

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Centre :** {{ $reservation->centre->first_name ?? 'Centre de camping' }}
**Arrivée :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }}
**Départ :** {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
**Durée :** {{ \Carbon\Carbon::parse($reservation->date_debut)->diffInDays($reservation->date_fin) + 1 }} nuit(s)
**Voyageurs :** {{ $reservation->nbr_place }}
**Total :** {{ number_format($reservation->total_price, 2) }} TND
**Statut :** En attente de confirmation
@endcomponent

@if($reservation->serviceItems && $reservation->serviceItems->count() > 0)
### Services demandés

@component('mail::table')
| Service | Qté | Prix unitaire | Sous-total |
|---------|-----|----------------|------------|
@foreach($reservation->serviceItems as $item)
| {{ $item->service_name }} | {{ $item->quantity }} {{ $item->unit }} | {{ number_format($item->unit_price, 2) }} TND | {{ number_format($item->subtotal, 2) }} TND |
@endforeach
@endcomponent
@endif

Vous recevrez un autre e-mail dès que le centre confirmera ou refusera votre demande.

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
Voir mes réservations
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Réservation n° {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
