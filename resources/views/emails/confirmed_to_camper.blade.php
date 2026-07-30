@component('mail::message')
# Réservation confirmée

Bonjour **{{ $reservation->user->first_name ?? 'cher client' }}**,

Votre réservation a été **confirmée** par le fournisseur.

@component('mail::panel')
**Article :** {{ $reservation->materielle->nom ?? 'N/A' }}
**Type :** {{ $reservation->type_reservation === 'location' ? 'Location' : 'Achat' }}
**Quantité :** {{ $reservation->quantite }}
**Montant total :** {{ number_format($reservation->montant_total, 2) }} TND
@if($reservation->type_reservation === 'location')
**Période :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
@endif
**Livraison :** {{ $reservation->mode_livraison === 'delivery' ? 'Livraison à domicile' : 'Retrait sur place' }}
@endcomponent

---

## Votre code PIN : `{{ $pin }}`

@component('mail::panel')
⚠️ **Important :** Présentez ce code PIN au fournisseur **lors du retrait de votre matériel**. Sans ce code, la remise ne peut pas être confirmée.

Gardez ce code confidentiel et ne le communiquez qu'au fournisseur.
@endcomponent

@if($reservation->type_reservation === 'location')
### Rappel des conditions de location

- Le matériel doit être restitué avant le **{{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}**.
- Votre carte d'identité (CIN) a été enregistrée pour cette réservation à titre de garantie légale.
- Tout matériel non restitué à temps peut faire l'objet de poursuites.
- Le paiement ne sera versé au fournisseur qu'après confirmation du retour du matériel.
@else
### Rappel des conditions de vente

- La vente est définitive. Aucun retour n'est accepté.
- Le paiement sera versé au fournisseur après confirmation de la livraison via votre code PIN.
@endif

Merci de faire confiance à **TunisiaCamp** !

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'success'])
Voir ma réservation
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
