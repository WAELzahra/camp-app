@component('mail::message')
# Bienvenue sur TunisiaCamp

Bonjour {{ $user?->first_name ?? 'là' }},

Merci de rejoindre **TunisiaCamp**. Pour finaliser votre inscription, veuillez vérifier votre adresse e-mail avec le code ci-dessous :

@component('mail::panel')
<div style="text-align: center; padding: 20px;">
    <div style="font-size: 36px; font-weight: bold; letter-spacing: 10px; color: #0E3D38;">
        {{ $code }}
    </div>
    <div style="font-size: 14px; color: #666; margin-top: 10px;">
        Code de vérification
    </div>
</div>
@endcomponent

**Important :**
- Ce code expire dans 15 minutes
- Saisissez-le sur la page de vérification
- Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail

@component('mail::button', ['url' => config('app.frontend_url') . '/verify-email', 'color' => 'success'])
Aller à la page de vérification
@endcomponent

Besoin d'aide ? Contactez-nous à [{{ $supportEmail }}](mailto:{{ $supportEmail }})

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
