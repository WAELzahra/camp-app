@component('mail::message')
# Bienvenue sur TunisiaCamp

Bonjour {{ $user?->first_name ?? 'là' }},

Merci de rejoindre **TunisiaCamp**, la plateforme dédiée à la découverte de destinations de camping, de guides et d'expériences en pleine nature partout en Tunisie.

Pour activer votre compte, veuillez vérifier votre adresse e-mail à l'aide du code ci-dessous.

---

## Votre code de vérification

Saisissez le **code à 6 chiffres** suivant sur le site TunisiaCamp :

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

---

## Ce que vous pouvez faire sur TunisiaCamp

- Découvrir des lieux de camping partout en Tunisie
- Entrer en contact avec des guides et des groupes de camping expérimentés
- Organiser et gérer vos sorties en plein air
- Réserver des services et du matériel de camping

---

## Besoin d'aide ?

Si vous n'êtes pas à l'origine de cette création de compte, aucune action n'est requise de votre part.

Pour toute assistance, contactez-nous à
[{{ $supportEmail }}](mailto:{{ $supportEmail }})

---

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Cette vérification expirera le {{ $expiresAt->locale('fr')->translatedFormat('j F Y \\à H:i') }}.
@endcomponent
@endcomponent
