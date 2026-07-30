@component('mail::message')
# Réservation modifiée

Bonjour **{{ $reservation->centre->first_name ?? 'cher gestionnaire' }}**,

Un campeur a modifié sa réservation. Veuillez consulter les nouveaux détails et la confirmer ou la refuser.

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Campeur :** {{ ($reservation->user->first_name ?? '') . ' ' . ($reservation->user->last_name ?? '') }}
**Arrivée :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }}
**Départ :** {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
**Durée :** {{ \Carbon\Carbon::parse($reservation->date_debut)->diffInDays($reservation->date_fin) + 1 }} nuit(s)
**Voyageurs :** {{ $reservation->nbr_place }}
**Nouveau total :** {{ number_format($reservation->total_price, 2) }} TND
**Statut :** En attente de reconfirmation
@endcomponent

@if($reservation->serviceItems && $reservation->serviceItems->count() > 0)
### Services mis à jour

@component('mail::table')
| Service | Qté | Prix unitaire | Sous-total |
|---------|-----|----------------|------------|
@foreach($reservation->serviceItems as $item)
| {{ $item->service_name }} | {{ $item->quantity }} {{ $item->unit }} | {{ number_format($item->unit_price, 2) }} TND | {{ number_format($item->subtotal, 2) }} TND |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
Consulter la réservation
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Réservation n° {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
