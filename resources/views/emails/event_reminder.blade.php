@component('mail::message')
# Rappel d'événement

Bonjour,

Ceci est un rappel pour l'événement **{{ $event->title }}** organisé par {{ trim(($event->group->first_name ?? '').' '.($event->group->last_name ?? '')) ?: 'le groupe' }}.

**Date de début :** {{ \Carbon\Carbon::parse($event->start_date)->locale('fr')->translatedFormat('d F Y à H:i') }}

Merci de votre participation !

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
