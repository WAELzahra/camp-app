@component('mail::message')
# Réinitialisation de votre mot de passe

Bonjour {{ $userName }},

Un administrateur a réinitialisé votre mot de passe TunisiaCamp. Voici votre nouveau mot de passe temporaire :

@component('mail::panel')
<div style="
    text-align: center;
    font-size: 22px;
    font-weight: 600;
    letter-spacing: 2px;
    color: #0E3D38;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
">
    {{ $newPassword }}
</div>
@endcomponent

Pour votre sécurité, nous vous recommandons de le changer dès votre prochaine connexion.

@component('mail::button', ['url' => $loginUrl, 'color' => 'primary'])
Se connecter
@endcomponent

Si vous n'êtes pas à l'origine de cette demande, contactez immédiatement notre support.

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
