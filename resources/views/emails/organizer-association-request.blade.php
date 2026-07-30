@component('mail::message')
# Nouvelle demande d'association

@php
$organizerName = trim(($link->organizer->first_name ?? '') . ' ' . ($link->organizer->last_name ?? '')) ?: ($link->organizer->email ?? 'Un organisateur');
$supplierName  = trim(($link->supplier->first_name  ?? '') . ' ' . ($link->supplier->last_name  ?? '')) ?: ($link->supplier->email ?? 'Vous');
@endphp

Bonjour **{{ $supplierName }}**,

L'organisateur **{{ $organizerName }}** souhaite s'associer avec vous sur TunisiaCamp afin de proposer votre matériel pendant ses événements.

@if($link->message)
@component('mail::panel')
**Message de {{ $organizerName }} :**

{{ $link->message }}
@endcomponent
@endif

@component('mail::panel')
**N° de demande :** #{{ $link->id }}
**De :** {{ $organizerName }} ({{ $link->organizer->email ?? '—' }})
**Envoyée le :** {{ $link->created_at->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent

En acceptant cette demande, votre matériel disponible sera proposé aux campeurs réservant les événements organisés par **{{ $organizerName }}**.

@component('mail::button', ['url' => $frontendUrl . '/settings/profile?tab=suppliers', 'color' => 'primary'])
Voir et répondre à la demande
@endcomponent

Vous pouvez accepter ou refuser cette demande depuis votre tableau de bord fournisseur.

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Si vous n'attendiez pas cette demande, vous pouvez ignorer cet e-mail en toute sécurité. N° de demande : {{ $link->id }}
@endcomponent
@endcomponent
