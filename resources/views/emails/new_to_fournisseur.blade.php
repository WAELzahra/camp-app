@component('mail::message')
# Nouvelle réservation reçue

Bonjour **{{ $camper->first_name ?? 'cher fournisseur' }}**,

Vous avez reçu une **nouvelle demande de réservation** sur TunisiaCamp.

@component('mail::panel')
**Client :** {{ $camper->first_name }} {{ $camper->last_name }}
**E-mail :** {{ $camper->email }}
**Article :** {{ $reservation->materielle->nom ?? 'N/A' }}
**Type :** {{ $reservation->type_reservation === 'location' ? 'Location' : 'Achat' }}
**Quantité :** {{ $reservation->quantite }}
**Montant :** {{ number_format($reservation->montant_total, 2) }} TND
@if($reservation->type_reservation === 'location')
**Période :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
@endif
**Livraison :** {{ $reservation->mode_livraison === 'delivery' ? 'Livraison à domicile' : 'Retrait sur place' }}
@if($reservation->adresse_livraison)
**Adresse :** {{ $reservation->adresse_livraison }}
@endif
@endcomponent

Connectez-vous à votre tableau de bord fournisseur pour **confirmer ou refuser** cette réservation.

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'primary'])
Gérer mes réservations
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
