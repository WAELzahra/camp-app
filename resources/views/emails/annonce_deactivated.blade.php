@component('mail::message')
# Annonce désactivée

Bonjour {{ $user->first_name ?? 'cher utilisateur' }},

Nous vous informons que votre annonce a été **désactivée par l'administrateur**.

@component('mail::panel')
**Description de l'annonce :** {{ $annonce->description }}
@endcomponent

Si vous pensez qu'il s'agit d'une erreur, veuillez nous contacter.

Merci de votre compréhension,
**L'équipe TunisiaCamp**
@endcomponent
