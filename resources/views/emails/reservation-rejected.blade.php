@component('mail::message')
# Réservation refusée

Bonjour {{ $user->first_name ?? 'cher client' }},

Nous sommes désolés de vous informer que votre réservation a été **refusée**.

@if($reservation)
**Détails de la réservation :**
**N° :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
@if($reservation->date_debut)
**Dates :** {{ \Carbon\Carbon::parse($reservation->date_debut)->locale('fr')->translatedFormat('d/m/Y') }} au {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
@endif
@if($reservation->nbr_place)
**Nombre de voyageurs :** {{ $reservation->nbr_place }}
@endif
@if($reservation->total_price)
**Total :** {{ number_format($reservation->total_price, 2) }} TND
@endif
@endif

@if($reason)
## Motif du refus

@component('mail::panel')
{{ $reason }}
@endcomponent
@endif

## Que pouvez-vous faire ?

1. **Modifier votre réservation** — vous pouvez la modifier et la soumettre à nouveau
2. **Choisir d'autres dates** — essayez de réserver pour des dates différentes
3. **Contacter le support** — si vous avez des questions sur cette décision
4. **Explorer d'autres options** — consultez les disponibilités d'autres centres

@component('mail::button', ['url' => $frontendUrl . '/reservations', 'color' => 'primary'])
Voir mes réservations
@endcomponent

@if($reservation)
@component('mail::button', ['url' => $frontendUrl . '/search', 'color' => 'success'])
Chercher d'autres dates
@endcomponent
@endif

## Besoin d'aide ?

Si vous souhaitez comprendre pourquoi votre réservation a été refusée ou discuter d'alternatives, contactez notre équipe support.

📞 **Contacter le support :** [{{ $supportEmail }}](mailto:{{ $supportEmail }})

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est une notification automatique, merci de ne pas y répondre directement.
@endcomponent
@endcomponent
