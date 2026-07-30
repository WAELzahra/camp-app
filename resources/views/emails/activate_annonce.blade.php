@component('mail::message')
# Annonce activée

Bonjour {{ $user->first_name ?? 'cher utilisateur' }},

Félicitations ! Votre annonce a été activée par l'administrateur.

Elle est désormais visible publiquement sur la plateforme.

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
