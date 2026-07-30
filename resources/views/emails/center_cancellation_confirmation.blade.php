@component('mail::message')
# Confirmation d'annulation

Bonjour {{ $centerName }},

Vous avez annulé la réservation d'un client. Cet e-mail confirme cette action pour vos archives.

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
**Places libérées :** {{ $vacantSpots }}

---

## Remboursement déjà traité

Cette annulation étant à votre initiative, le client a été **automatiquement remboursé intégralement** ({{ number_format($totalPrice, 2) }} TND) sur son wallet TunisiaCamp — aucune action de votre part n'est nécessaire pour ce remboursement.

@if($platformFee > 0)
@component('mail::panel')
**Frais d'annulation plateforme prélevés sur votre solde :** {{ number_format($platformFee, 2) }} TND
@endcomponent
@endif

---

## Prochaines étapes

1. **Mettre à jour vos disponibilités** — {{ $vacantSpots }} place(s) redevenue(s) disponible(s)
2. **Informer votre équipe** si nécessaire

---

## Support

Pour toute question concernant cette annulation, contactez-nous à [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

Cordialement,

**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est une confirmation automatique. Référence : {{ $reservationId }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
