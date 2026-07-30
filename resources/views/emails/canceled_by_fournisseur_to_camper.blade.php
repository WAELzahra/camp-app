@component('mail::message')
# Votre réservation a été annulée

Bonjour **{{ $reservation->user->first_name ?? 'cher client' }}**,

Malheureusement, le fournisseur a **annulé votre réservation**.

@component('mail::panel')
**Article :** {{ $reservation->materielle->nom ?? 'N/A' }}
**Type :** {{ $reservation->type_reservation === 'location' ? 'Location' : 'Achat' }}
**Quantité :** {{ $reservation->quantite }}
**Montant :** {{ number_format($reservation->montant_total, 2) }} TND
@if($reservation->type_reservation === 'location')
**Période :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
@endif
@endcomponent

Nous nous excusons pour la gêne occasionnée. Vous pouvez parcourir le reste du matériel disponible sur TunisiaCamp et soumettre une nouvelle demande.

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'primary'])
Parcourir le matériel
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
