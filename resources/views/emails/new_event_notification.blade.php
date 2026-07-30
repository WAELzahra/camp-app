@component('mail::message')
# Nouvel événement

Bonjour {{ $userName }},

Un nouvel événement a été créé par un groupe que vous suivez !

@component('mail::panel')
**Titre :** {{ $event->title }}
**Date de départ :** {{ \Carbon\Carbon::parse($event->start_date)->locale('fr')->translatedFormat('d F Y') }}
**Date de retour :** {{ \Carbon\Carbon::parse($event->end_date)->locale('fr')->translatedFormat('d F Y') }}
**Prix par place :** {{ number_format($event->price, 2) }} TND
@endcomponent

Connectez-vous sur la plateforme pour en savoir plus et réserver votre place !

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173') . '/events/' . $event->slug, 'color' => 'primary'])
Voir l'événement
@endcomponent

À bientôt,
**L'équipe TunisiaCamp**
@endcomponent
