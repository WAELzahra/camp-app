@component('mail::message')
# Statut de compte mis à jour

Bonjour {{ $userName }},

Le statut de votre compte a été modifié par un administrateur.

@if($statusValue == 1)
@component('mail::panel')
**✅ Compte activé**

Vous avez maintenant accès à toutes les fonctionnalités de la plateforme.
@endcomponent

@component('mail::button', ['url' => $loginUrl, 'color' => 'success'])
Se connecter
@endcomponent
@else
@component('mail::panel')
**❌ Compte désactivé**

Votre compte a été désactivé. Pour plus d'informations, contactez notre équipe de support.
@endcomponent
@endif

---

**Support client :** [{{ $supportEmail }}](mailto:{{ $supportEmail }})

Cordialement,
**L'équipe {{ $appName }}**

@component('mail::subcopy')
Cet e-mail a été envoyé à {{ $userEmail }}.
@endcomponent
@endcomponent
