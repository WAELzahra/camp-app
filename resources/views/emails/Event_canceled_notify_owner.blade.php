@component('mail::message')
# Participant Canceled Their Reservation

Dear {{ $ownerName }},

A participant has canceled their reservation for your event **{{ $eventTitle }}**. The spots are now available again.

**Cancellation Reference:** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}
**Canceled At:** {{ $canceledAt }}

---

## Participant Information

**Name:** {{ $userName }}
**Email:** {{ $userEmail }}
**Phone:** {{ $userPhone }}

---

## Reservation Details

**Event:** {{ $eventTitle }}
**Event Dates:** {{ \Carbon\Carbon::parse($eventStartDate)->format('F j, Y') }} – {{ \Carbon\Carbon::parse($eventEndDate)->format('F j, Y') }}
**Spots Released:** {{ $nbrPlace }}
**Reservation Value:** {{ number_format($totalPrice, 2) }} TND

---

## What You Should Do

1. **Update your headcount** – {{ $nbrPlace }} spot(s) are now available again
2. **Check waitlist** – If you have people waiting, reach out to them
3. **Review event status** – Log in to manage your event participants

@component('mail::button', ['url' => config('app.frontend_url') . '/settings?tab=events', 'color' => 'info'])
Manage My Events
@endcomponent

If you have questions about this cancellation, you can reach the participant at **{{ $userEmail }}**.

Sincerely,

**TunisiaCamp Team**

@component('mail::subcopy')
This is an automated notification. For support: support@tunisiacamp.com
Cancellation ID: #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent