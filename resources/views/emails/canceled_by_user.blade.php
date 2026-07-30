@component('mail::message')
# Réservation annulée par le client

Bonjour {{ $centerName }},

Un client a annulé sa réservation auprès de votre centre. Voici les détails.

**Référence d'annulation :** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}
**Date d'annulation :** {{ $canceledAt }}

---

## Détails de l'annulation

### Informations sur le client
**Nom :** {{ $userName }}
**E-mail :** {{ $userEmail }}

### Détails de la réservation
**Dates :** {{ \Carbon\Carbon::parse($startDate)->locale('fr')->translatedFormat('d F Y') }} au {{ \Carbon\Carbon::parse($endDate)->locale('fr')->translatedFormat('d F Y') }}
**Valeur totale :** **{{ number_format($totalPrice, 2) }} TND**
**Nombre de services :** {{ $serviceCount }}
**Note du client :** {{ $note ?? 'Aucune note fournie' }}

---

## Remboursement

@if($refundAmount !== null)
Le remboursement a déjà été traité automatiquement : **{{ number_format($refundAmount, 2) }} TND** ont été crédités sur le wallet du client.
@if($refundNote)
**Motif :** {{ $refundNote }}
@endif

Aucune action n'est requise de votre part concernant ce remboursement.
@else
Aucun remboursement n'était applicable pour cette réservation.
@endif

---

## Prochaines étapes

1. **Mettre à jour vos disponibilités** — libérez les dates dans votre calendrier
2. **Mettre à jour vos registres** — la réservation est déjà marquée comme annulée

@component('mail::button', ['url' => config('app.frontend_url') . '/center/reservations', 'color' => 'info'])
Gérer mes réservations
@endcomponent

---

## Contact

**Client :** {{ $userName }} ({{ $userEmail }})
**Support TunisiaCamp :** [{{ $supportEmail }}](mailto:{{ $supportEmail }})

---

Cordialement,

**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est une notification automatique, merci de ne pas y répondre directement.
Support : {{ $supportEmail }} · Référence : {{ $reservationId }} · Traité le {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
