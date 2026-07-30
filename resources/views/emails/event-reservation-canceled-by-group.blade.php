@component('mail::message')
# Réservation annulée

Bonjour **{{ $reservation->name ?? 'cher participant' }}**,

Malheureusement, l'organisateur de l'événement a annulé votre réservation pour **{{ $event->title ?? 'l\'événement' }}**.

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Événement :** {{ $event->title ?? 'N/A' }}
**Date de l'événement :** {{ \Carbon\Carbon::parse($event->start_date ?? $event->date_debut ?? now())->locale('fr')->translatedFormat('d/m/Y') }}
**Places :** {{ $reservation->nbr_place }}
@endcomponent

@if($refundAmount !== null)
Cette annulation étant à l'initiative de l'organisateur, vous avez été **intégralement remboursé** : **{{ number_format($refundAmount, 2) }} TND** ont été crédités sur votre wallet TunisiaCamp, disponibles immédiatement.
@endif

Si vous pensez qu'il s'agit d'une erreur ou avez des questions, contactez le support TunisiaCamp à [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

@component('mail::button', ['url' => $frontendUrl . '/events', 'color' => 'primary'])
Découvrir d'autres événements
@endcomponent

Nous nous excusons pour la gêne occasionnée.

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Réservation n° {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
