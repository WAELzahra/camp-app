@component('mail::message')
# Reservation Request Received ✅

Hi **{{ $reservation->user->first_name ?? 'Valued Customer' }}**,

Your reservation request has been submitted successfully. The camping centre will review it and confirm shortly.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Centre:** {{ $reservation->centre->first_name ?? 'Camping Centre' }}
**Check-in:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('d/m/Y') }}
**Check-out:** {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
**Duration:** {{ \Carbon\Carbon::parse($reservation->date_debut)->diffInDays($reservation->date_fin) + 1 }} night(s)
**Guests:** {{ $reservation->nbr_place }}
**Total:** {{ number_format($reservation->total_price, 2) }} TND
**Status:** Pending confirmation
@endcomponent

@if($reservation->serviceItems && $reservation->serviceItems->count() > 0)
### Services Requested

@component('mail::table')
| Service | Qty | Unit Price | Subtotal |
|---------|-----|------------|----------|
@foreach($reservation->serviceItems as $item)
| {{ $item->service_name }} | {{ $item->quantity }} {{ $item->unit }} | {{ number_format($item->unit_price, 2) }} TND | {{ number_format($item->subtotal, 2) }} TND |
@endforeach
@endcomponent
@endif

You will receive another email once the centre confirms or rejects your request.

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
View My Reservations
@endcomponent

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Reservation ID: {{ $reservation->id }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent
