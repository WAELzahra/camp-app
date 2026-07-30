@component('mail::message')
# Réservation annulée par le centre

Bonjour {{ $userName }},

Nous sommes désolés de vous informer que votre réservation a été annulée par le centre. Voici les détails.

**Référence d'annulation :** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}
**Date d'annulation :** {{ $canceledAt }}

---

## Détails de l'annulation

### Informations sur le centre
**Nom du centre :** {{ $centerName }}
**E-mail de contact :** {{ $centerEmail }}
**Téléphone :** {{ $centerPhone }}

### Détails de la réservation
**Dates :** {{ \Carbon\Carbon::parse($startDate)->locale('fr')->translatedFormat('d F Y') }} au {{ \Carbon\Carbon::parse($endDate)->locale('fr')->translatedFormat('d F Y') }}
**Montant total :** **{{ number_format($totalPrice, 2) }} TND**

---

## Remboursement

Cette annulation étant à l'initiative du centre, vous bénéficiez d'un **remboursement intégral** de **{{ number_format($totalPrice, 2) }} TND**.

- Si vous avez payé via votre **wallet TunisiaCamp**, le montant a déjà été recrédité sur votre solde.
- Si vous avez payé par **virement bancaire**, notre équipe traite votre remboursement et vous contactera séparément.

@component('mail::button', ['url' => config('app.frontend_url') . '/zones', 'color' => 'success'])
Explorer d'autres centres
@endcomponent

---

## Besoin d'aide ?

### Contacter le centre
**E-mail :** [{{ $centerEmail }}](mailto:{{ $centerEmail }})
**Téléphone :** {{ $centerPhone }}

### Support TunisiaCamp
**E-mail :** [{{ $supportEmail }}](mailto:{{ $supportEmail }})

@component('mail::button', ['url' => config('app.frontend_url') . '/help', 'color' => 'info'])
Obtenir de l'aide
@endcomponent

---

Nous nous excusons pour la gêne occasionnée et espérons vous aider à trouver un autre hébergement.

Cordialement,

**Le support TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Pour toute question sur votre remboursement, contactez : {{ $supportEmail }}
Référence d'annulation : {{ $reservationId }} · Traité le {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
