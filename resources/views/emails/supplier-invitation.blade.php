@component('mail::message')
# Vous êtes invité(e) sur TunisiaCamp !

@php
$organizerName = trim(($organizer->first_name ?? '') . ' ' . ($organizer->last_name ?? '')) ?: ($organizer->email ?? 'Un organisateur');
$registerUrl   = $frontendUrl . '/register?token=' . $invitation->token . '&email=' . urlencode($invitation->email) . '&role=fournisseur';
@endphp

Bonjour,

**{{ $organizerName }}** vous invite à rejoindre **TunisiaCamp** en tant que fournisseur. Une fois inscrit, votre matériel sera proposé aux campeurs qui réservent les événements organisés par {{ $organizerName }}.

@if($organizerMessage)
@component('mail::panel')
**Message de {{ $organizerName }} :**

{{ $organizerMessage }}
@endcomponent
@endif

@component('mail::panel')
**Détails de l'invitation :**
- **Invité par :** {{ $organizerName }}
- **Votre e-mail :** {{ $invitation->email }}
- **Expire le :** {{ \Carbon\Carbon::parse($invitation->expires_at)->locale('fr')->translatedFormat('d/m/Y') }}
@endcomponent

Cliquez sur le bouton ci-dessous pour créer votre compte fournisseur. Votre code d'invitation est pré-rempli automatiquement.

@component('mail::button', ['url' => $registerUrl, 'color' => 'primary'])
Créer mon compte fournisseur
@endcomponent

**Que se passe-t-il ensuite ?**
1. Créez votre compte fournisseur
2. Ajoutez votre matériel au catalogue
3. Percevez des revenus lorsque des campeurs réservent votre matériel pendant des événements !

Cette invitation expire le **{{ \Carbon\Carbon::parse($invitation->expires_at)->locale('fr')->translatedFormat('d/m/Y') }}**.

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Si vous n'attendiez pas cette invitation, vous pouvez ignorer cet e-mail en toute sécurité. Le lien expirera automatiquement. Jeton d'invitation : {{ $invitation->token }}
@endcomponent
@endcomponent
