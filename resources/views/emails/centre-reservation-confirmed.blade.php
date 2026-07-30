@component('mail::message')
# Réservation confirmée

Bonjour {{ $reservation->user->first_name ?? ($reservation->user->name ?? 'cher client') }},

Votre réservation a bien été confirmée. Merci de votre confiance envers TunisiaCamp — nous avons hâte de vous accueillir pour un séjour de camping mémorable.

**Référence de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Date de confirmation :** {{ now()->locale('fr')->translatedFormat('d F Y') }}

---

## Récapitulatif

### Le centre
**Nom du centre :** {{ $reservation->centre->name ?? 'Centre de camping' }}
**Lieu :** {{ optional(optional($reservation->centre->profile)->profileCentre)->adresse ?? 'Tunisie' }}
**Dates du séjour :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d F Y') }} au {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d F Y') }}
**Durée :** {{ \Carbon\Carbon::parse($reservation->date_debut)->diffInDays($reservation->date_fin) + 1 }} nuits
**Voyageurs :** {{ $reservation->nbr_place }} personne(s)

@if($reservation->type)
**Formule :** {{ $reservation->type }}
@endif

@if($reservation->note)
**Notes complémentaires :** {{ $reservation->note }}
@endif

---

## Récapitulatif financier

**Montant total :** **{{ number_format($reservation->total_price, 2) }} TND**

@if($reservation->serviceItems && $reservation->serviceItems->count() > 0)
@component('mail::table')
| Service | Quantité | Prix unitaire | Sous-total |
|---------|----------|----------------|------------|
@foreach($reservation->serviceItems as $item)
| **{{ $item->service_name }}** | {{ $item->quantity }} {{ $item->unit }} | {{ number_format($item->unit_price, 2) }} TND | {{ number_format($item->subtotal, 2) }} TND |
@endforeach
| | | **Total** | **{{ number_format($reservation->total_price, 2) }} TND** |
@endcomponent
@endif

---

## À savoir avant votre arrivée

- **Arrivée :** à partir de {{ $checkInTime }}
- **Départ :** avant {{ $checkOutTime }}
- **Arrivée anticipée / départ tardif :** selon disponibilité, des frais supplémentaires peuvent s'appliquer
- Munissez-vous d'une pièce d'identité officielle et de cette confirmation de réservation

### Contact du centre
**E-mail :** {{ $reservation->centre->email ?? 'Communiqué séparément' }}
**Téléphone :** {{ optional($reservation->centre->profile)->profileCentre->contact_phone ?? $reservation->centre->phone_number ?? 'Disponible dans les informations du centre' }}

@component('mail::button', ['url' => $reservationDetailsUrl, 'color' => 'primary'])
Voir les détails complets
@endcomponent

---

## Besoin d'aide ?

**Support TunisiaCamp :** [{{ $supportEmail }}](mailto:{{ $supportEmail }})

Nous vous souhaitons un séjour agréable et mémorable en Tunisie.

Cordialement,

**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique, merci de ne pas y répondre directement.
Pour toute question : {{ $supportEmail }} · Référence : {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
