@component('mail::message')
# Événement validé !

Votre événement **{{ $event->title ?? $event->description }}** a été activé par l'administrateur.

Il est désormais visible par les campeurs.

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
