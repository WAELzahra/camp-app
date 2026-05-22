@component('mail::message')
# New Association Request

@php
$organizerName = trim(($link->organizer->first_name ?? '') . ' ' . ($link->organizer->last_name ?? '')) ?: ($link->organizer->email ?? 'An organizer');
$supplierName  = trim(($link->supplier->first_name  ?? '') . ' ' . ($link->supplier->last_name  ?? '')) ?: ($link->supplier->email ?? 'You');
@endphp

Hi **{{ $supplierName }}**,

The organizer **{{ $organizerName }}** would like to associate with you on TunisiaCamp to offer your equipment during their events.

@if($link->message)
@component('mail::panel')
**Message from {{ $organizerName }}:**

{{ $link->message }}
@endcomponent
@endif

@component('mail::panel')
**Request ID:** #{{ $link->id }}
**From:** {{ $organizerName }} ({{ $link->organizer->email ?? '—' }})
**Sent:** {{ $link->created_at->format('d/m/Y H:i') }}
@endcomponent

By accepting this request, your available equipment will be displayable to campers booking events organized by **{{ $organizerName }}**.

@component('mail::button', ['url' => $frontendUrl . '/settings/profile?tab=suppliers', 'color' => 'primary'])
View & Respond to Request
@endcomponent

You can accept or reject this request from your supplier dashboard.

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
If you were not expecting this request, you can safely ignore this email. Request ID: {{ $link->id }}
@endcomponent
@endcomponent
