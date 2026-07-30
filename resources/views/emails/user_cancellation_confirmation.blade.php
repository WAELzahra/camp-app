@component('mail::message')
# Confirmation d'annulation

Bonjour {{ $userName }},

Votre annulation de réservation a bien été traitée. Conservez cette confirmation pour vos dossiers.

**N° d'annulation :** {{ $cancellationNumber }}
**Date d'annulation :** {{ $canceledAt }}

---

## Récapitulatif

### Détails de la réservation
**Centre :** {{ $centerName }}
**Dates :** {{ \Carbon\Carbon::parse($startDate)->locale('fr')->translatedFormat('d F Y') }} au {{ \Carbon\Carbon::parse($endDate)->locale('fr')->translatedFormat('d F Y') }}
**Montant initial :** **{{ number_format($totalPrice, 2) }} TND**
**N° de réservation :** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}

---

## Remboursement

@if($refundAmount !== null)
@component('mail::panel')
**Montant remboursé sur votre wallet TunisiaCamp :** {{ number_format($refundAmount, 2) }} TND
@if($refundNote)
**Motif :** {{ $refundNote }}
@endif
@endcomponent

Ce montant a déjà été crédité sur votre solde TunisiaCamp et est disponible immédiatement.
@elseif($paymentMethod === 'wallet')
Aucun montant n'était dû sur cette réservation, aucun remboursement n'a donc été nécessaire.
@else
Cette réservation n'a pas été payée via votre wallet TunisiaCamp. Si un virement bancaire a déjà été effectué, notre équipe support vous contactera séparément au sujet de votre remboursement.
@endif

---

## Contact

**Centre :** {{ $centerName }}
**Support TunisiaCamp :** [{{ $supportEmail }}](mailto:{{ $supportEmail }})

---

Merci d'avoir utilisé TunisiaCamp. Nous espérons vous accueillir à nouveau prochainement.

Cordialement,

**Le support TunisiaCamp**

@component('mail::subcopy')
Ceci est une confirmation automatique. Pour toute question sur votre remboursement : {{ $supportEmail }}
Référence : {{ $cancellationNumber }} · Traité le {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
