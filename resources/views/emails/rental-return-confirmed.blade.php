@component('mail::message')
# Retour de matériel confirmé

Bonjour **{{ $reservation->user->first_name ?? 'cher client' }}**,

Le fournisseur a confirmé la restitution de votre matériel loué. Votre location est maintenant terminée.

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Article :** {{ $reservation->materielle->nom ?? 'N/A' }}
**Fournisseur :** {{ ($reservation->fournisseur->first_name ?? '') . ' ' . ($reservation->fournisseur->last_name ?? '') }}
**Période de location :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
**Quantité :** {{ $reservation->quantite }}
**Total payé :** {{ number_format($reservation->montant_total, 2) }} TND
**Restitué le :** {{ \Carbon\Carbon::parse($reservation->returned_at)->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent

Merci d'avoir utilisé TunisiaCamp. Nous espérons que le matériel vous a donné satisfaction !

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'success'])
Voir mes réservations
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Réservation n° {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
