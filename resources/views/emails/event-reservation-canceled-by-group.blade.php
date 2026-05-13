@component('mail::message')
# Reservation Cancelled ❌

Hi **{{ $reservation->name ?? 'Participant' }}**,

Unfortunately, the event organiser has cancelled your reservation for **{{ $event->title ?? 'the event' }}**.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Event:** {{ $event->title ?? 'N/A' }}
**Event Date:** {{ \Carbon\Carbon::parse($event->start_date ?? $event->date_debut ?? now())->format('d/m/Y') }}
**Spots:** {{ $reservation->nbr_place }}
@endcomponent

If you believe this is a mistake or have questions, please contact TunisiaCamp support.

@component('mail::button', ['url' => $frontendUrl . '/events', 'color' => 'primary'])
Browse Other Events
@endcomponent

We apologise for the inconvenience.

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Reservation ID: {{ $reservation->id }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent
