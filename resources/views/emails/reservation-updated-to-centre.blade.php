@component('mail::message')
# Reservation Modified ✏️

Hi **{{ $reservation->centre->first_name ?? 'Centre Manager' }}**,

A camper has modified their reservation. Please review the updated details and re-confirm or reject.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Camper:** {{ ($reservation->user->first_name ?? '') . ' ' . ($reservation->user->last_name ?? '') }}
**Check-in:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('d/m/Y') }}
**Check-out:** {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
**Duration:** {{ \Carbon\Carbon::parse($reservation->date_debut)->diffInDays($reservation->date_fin) + 1 }} night(s)
**Guests:** {{ $reservation->nbr_place }}
**New Total:** {{ number_format($reservation->total_price, 2) }} TND
**Status:** Awaiting re-confirmation
@endcomponent

@if($reservation->serviceItems && $reservation->serviceItems->count() > 0)
### Updated Services

@component('mail::table')
| Service | Qty | Unit Price | Subtotal |
|---------|-----|------------|----------|
@foreach($reservation->serviceItems as $item)
| {{ $item->service_name }} | {{ $item->quantity }} {{ $item->unit }} | {{ number_format($item->unit_price, 2) }} TND | {{ number_format($item->subtotal, 2) }} TND |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
Review Reservation
@endcomponent

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Reservation ID: {{ $reservation->id }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent
