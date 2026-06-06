@component('mail::message')
# You're Invited to TunisiaCamp!

@php
$organizerName = trim(($organizer->first_name ?? '') . ' ' . ($organizer->last_name ?? '')) ?: ($organizer->email ?? 'An organizer');
$registerUrl   = $frontendUrl . '/register?token=' . $invitation->token . '&email=' . urlencode($invitation->email) . '&role=fournisseur';
@endphp

Hi there!

**{{ $organizerName }}** has invited you to join **TunisiaCamp** as a supplier (fournisseur). Once registered, your equipment and gear will be available for campers booking events organized by {{ $organizerName }}.

@if($organizerMessage)
@component('mail::panel')
**Message from {{ $organizerName }}:**

{{ $organizerMessage }}
@endcomponent
@endif

@component('mail::panel')
**Invitation details:**
- **Invited by:** {{ $organizerName }}
- **Your email:** {{ $invitation->email }}
- **Expires:** {{ \Carbon\Carbon::parse($invitation->expires_at)->format('d/m/Y') }} (7 days)
@endcomponent

Click the button below to create your supplier account. Your invitation code is pre-filled automatically.

@component('mail::button', ['url' => $registerUrl, 'color' => 'primary'])
Create My Supplier Account
@endcomponent

**What happens next?**
1. Register your supplier account
2. Add your equipment to the catalog
3. Get revenue when campers book your gear during events!

This invitation expires on **{{ \Carbon\Carbon::parse($invitation->expires_at)->format('d/m/Y') }}**.

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
If you were not expecting this invitation, you can safely ignore this email. The link will expire automatically. Invitation token: {{ $invitation->token }}
@endcomponent
@endcomponent
