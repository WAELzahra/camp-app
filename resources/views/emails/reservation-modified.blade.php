@component('mail::message')
# Réservation modifiée

Bonjour {{ $reservation->user->first_name ?? 'cher client' }},

Votre réservation a été examinée par le centre. Certains services n'ont pas pu être confirmés en raison de contraintes de disponibilité.

**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Dates du séjour :** {{ $startDate->locale('fr')->translatedFormat('d F Y') }} au {{ $endDate->locale('fr')->translatedFormat('d F Y') }}
**Durée :** {{ $duration }} nuit{{ $duration > 1 ? 's' : '' }}
**Nombre de voyageurs :** {{ $reservation->nbr_place }}
**Modifiée le :** {{ $modificationDate->locale('fr')->translatedFormat('d F Y \à H:i') }}

@if($generalReason)
**Note du centre :** {{ $generalReason }}
@endif

---

## Services confirmés

@if($acceptedServices->count() > 0)
@component('mail::table')
| Service | Quantité | Prix unitaire | Sous-total |
|---------|----------|----------------|------------|
@foreach($acceptedServices as $service)
| {{ $service->service_name }} | {{ $service->quantity }} {{ $service->unit }} | {{ number_format($service->unit_price, 2) }} TND | {{ number_format($service->subtotal, 2) }} TND |
@endforeach
@endcomponent
@else
Aucun service n'a été confirmé.
@endif

---

## Services indisponibles

@if($rejectedServices->count() > 0)
@foreach($rejectedServices as $service)
### {{ $service->service_name }}
- **Quantité :** {{ $service->quantity }} {{ $service->unit }}
- **Motif :** {{ $service->rejection_reason ?? 'Non disponible' }}
- **Prix initial :** {{ number_format($service->subtotal, 2) }} TND

@endforeach
@else
Tous les services demandés ont été confirmés.
@endif

---

## Tarif mis à jour

**Total des services confirmés :** **{{ number_format($acceptedServices->sum('subtotal'), 2) }} TND**

@if($rejectedServices->count() > 0)
**Services retirés :** {{ number_format($rejectedServices->sum('subtotal'), 2) }} TND
@endif

---

## Prochaines étapes

1. Consultez le détail de la réservation modifiée
2. Contactez le centre si vous avez des questions sur les services indisponibles
3. Poursuivez avec les services confirmés, ou annulez si nécessaire

@component('mail::button', ['url' => $frontendUrl . '/reservations/' . $reservation->id, 'color' => 'primary'])
Voir la réservation complète
@endcomponent

---

## Besoin d'aide ?

Contactez le support TunisiaCamp à [{{ $supportEmail }}](mailto:{{ $supportEmail }})

@endcomponent
