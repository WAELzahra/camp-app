@component('mail::message')
# Event Reservation Received 🎉

Hi **{{ $reservation->name ?? 'Participant' }}**,

Your reservation for the event **{{ $event->title ?? 'Event' }}** has been registered. Complete your payment to confirm your spot.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Event:** {{ $event->title ?? 'N/A' }}
**Date:** {{ \Carbon\Carbon::parse($event->start_date ?? $event->date_debut ?? now())->format('d/m/Y') }}
**Spots Reserved:** {{ $reservation->nbr_place }}
**Total Price:** {{ number_format(($reservation->nbr_place ?? 1) * ($event->price ?? 0), 2) }} TND
**Status:** Pending payment
@endcomponent

Please complete your payment to secure your participation. Your spot will be held until payment is confirmed.

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
Complete Payment
@endcomponent

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Reservation ID: {{ $reservation->id }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent
