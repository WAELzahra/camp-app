@component('mail::message')
# Événement supprimé

Bonjour,

Nous vous informons que votre événement **{{ $event->title }}**, prévu pour le {{ \Carbon\Carbon::parse($event->start_date)->locale('fr')->translatedFormat('d F Y') }}, a été supprimé.

Si vous pensez qu'il s'agit d'une erreur ou si vous avez des questions, merci de contacter notre support.

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
