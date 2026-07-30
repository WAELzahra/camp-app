@component('mail::message')
# Statut de réservation mis à jour

Bonjour **{{ $recipientName }}**,

Le statut de votre réservation pour **{{ $itemName }}** a été mis à jour.

@component('mail::panel')
**Nouveau statut :** {{ $reservation->status }}
@if($oldStatus)
**Statut précédent :** {{ $oldStatus }}
@endif
@endcomponent

Si vous avez des questions, contactez notre équipe support.

Merci de faire confiance à TunisiaCamp !

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est une notification automatique.
@endcomponent
@endcomponent
