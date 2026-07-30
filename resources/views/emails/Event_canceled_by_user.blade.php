@component('mail::message')
# Réservation annulée

Bonjour {{ $userName }},

Votre réservation pour **{{ $eventTitle }}** a bien été annulée. Voici le récapitulatif.

**Référence d'annulation :** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}
**Annulée le :** {{ $canceledAt }}

---

## Récapitulatif de la réservation

**Événement :** {{ $eventTitle }}
**Dates :** {{ \Carbon\Carbon::parse($eventStartDate)->locale('fr')->translatedFormat('d F Y') }} – {{ \Carbon\Carbon::parse($eventEndDate)->locale('fr')->translatedFormat('d F Y') }}
**Places réservées :** {{ $nbrPlace }}
**Montant payé :** **{{ number_format($totalPrice, 2) }} TND**

---

## Remboursement

@if($refundAmount !== null)
@component('mail::panel')
**Montant remboursé sur votre wallet TunisiaCamp :** {{ number_format($refundAmount, 2) }} TND
@if($feeLabel)
**Motif :** {{ $feeLabel }}
@endif
@if($platformFee > 0)
**Dont frais d'annulation plateforme :** {{ number_format($platformFee, 2) }} TND
@endif
@endcomponent

Ce montant a déjà été crédité sur votre solde TunisiaCamp et est disponible immédiatement.
@elseif($paymentMethod === 'wallet')
Aucun montant n'était dû sur cette réservation, aucun remboursement n'a donc été nécessaire.
@else
Cette réservation n'a pas été payée via votre wallet TunisiaCamp. Si un virement bancaire a déjà été effectué, notre équipe support vous contactera séparément au sujet de votre remboursement.
@endif

@component('mail::button', ['url' => config('app.frontend_url') . '/events', 'color' => 'success'])
Découvrir d'autres événements
@endcomponent

---

Pour toute question, contactez-nous à [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

Cordialement,

**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique, merci de ne pas y répondre directement.
Support : {{ $supportEmail }} · Réservation n° {{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
