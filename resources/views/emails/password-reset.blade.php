@component('mail::message')
# Réinitialisation de mot de passe

Bonjour {{ $user?->first_name ?? 'là' }},

Utilisez ce code de vérification pour réinitialiser votre mot de passe TunisiaCamp :

@component('mail::panel')
<div style="
    text-align: center;
    font-size: 32px;
    font-weight: 600;
    letter-spacing: 8px;
    color: #0E3D38;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
">
    {{ $code }}
</div>

<p style="
    text-align: center;
    font-size: 13px;
    color: #6b7280;
    margin-top: 8px;
">
    Ce code expire dans {{ $expiresAt->locale('fr')->diffForHumans(null, true) }}
</p>
@endcomponent

@component('mail::button', ['url' => $frontendUrl, 'color' => 'primary'])
Réinitialiser mon mot de passe
@endcomponent

---

Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail.

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Expire le : {{ $expiresAt->locale('fr')->translatedFormat('j F Y \\à H:i') }}
@endcomponent
@endcomponent
