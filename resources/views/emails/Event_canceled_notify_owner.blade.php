@component('mail::message')
# Un participant a annulé sa réservation

Bonjour {{ $ownerName }},

Un participant a annulé sa réservation pour votre événement **{{ $eventTitle }}**. Les places sont de nouveau disponibles.

**Référence d'annulation :** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}
**Annulée le :** {{ $canceledAt }}

---

## Informations sur le participant

**Nom :** {{ $userName }}
**E-mail :** {{ $userEmail }}
**Téléphone :** {{ $userPhone }}

---

## Détails de la réservation

**Événement :** {{ $eventTitle }}
**Dates de l'événement :** {{ \Carbon\Carbon::parse($eventStartDate)->locale('fr')->translatedFormat('d F Y') }} – {{ \Carbon\Carbon::parse($eventEndDate)->locale('fr')->translatedFormat('d F Y') }}
**Places libérées :** {{ $nbrPlace }}
**Valeur de la réservation :** {{ number_format($totalPrice, 2) }} TND

---

## Ce que vous pouvez faire

1. **Mettre à jour vos effectifs** — {{ $nbrPlace }} place(s) sont de nouveau disponibles
2. **Consulter votre liste d'attente** — si des personnes sont en attente, contactez-les
3. **Vérifier le statut de l'événement** — connectez-vous pour gérer vos participants

@component('mail::button', ['url' => config('app.frontend_url') . '/settings?tab=events', 'color' => 'info'])
Gérer mes événements
@endcomponent

Pour toute question sur cette annulation, vous pouvez contacter le participant à **{{ $userEmail }}**.

Cordialement,

**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est une notification automatique. Support : {{ $supportEmail }}
Référence d'annulation : #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
