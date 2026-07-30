@component('mail::message')
# Réservation annulée

Bonjour,

Une réservation a été **annulée** sur votre boutique TunisiaCamp.

@component('mail::panel')
**Article :** {{ $reservation->materielle->nom ?? 'N/A' }}
**Type :** {{ $reservation->type_reservation === 'location' ? 'Location' : 'Achat' }}
**Quantité :** {{ $reservation->quantite }}
**Montant :** {{ number_format($reservation->montant_total, 2) }} TND
@if($reservation->type_reservation === 'location')
**Période :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
@endif
@endcomponent

Le stock a été automatiquement restauré.

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'primary'])
Voir mes réservations
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
