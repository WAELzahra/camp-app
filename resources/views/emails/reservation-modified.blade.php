@component('mail::message')
# Reservation Modified

Hello {{ $reservation->user->first_name ?? 'there' }},

Your reservation has been reviewed by the center. Some services could not be confirmed due to availability constraints.

**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}  
**Booking Dates:** {{ $startDate->format('F j, Y') }} to {{ $endDate->format('F j, Y') }}  
**Duration:** {{ $duration }} night{{ $duration > 1 ? 's' : '' }}  
**Number of Guests:** {{ $reservation->nbr_place }}  
**Modified On:** {{ $modificationDate->format('F j, Y \a\t g:i A') }}

@if($generalReason)
**Center's Note:** {{ $generalReason }}
@endif

---

## Services Confirmed

@if($acceptedServices->count() > 0)
@component('mail::table')
| Service | Quantity | Unit Price | Subtotal |
|---------|----------|------------|----------|
@foreach($acceptedServices as $service)
| {{ $service->service_name }} | {{ $service->quantity }} {{ $service->unit }} | {{ number_format($service->unit_price, 2) }} TND | {{ number_format($service->subtotal, 2) }} TND |
@endforeach
@endcomponent
@else
No services were confirmed.
@endif

---

## Services Unavailable

@if($rejectedServices->count() > 0)
@foreach($rejectedServices as $service)
### {{ $service->service_name }}
- **Quantity:** {{ $service->quantity }} {{ $service->unit }}
- **Reason:** {{ $service->rejection_reason ?? 'Not available' }}
- **Original Price:** {{ number_format($service->subtotal, 2) }} TND

@endforeach
@else
All requested services have been confirmed.
@endif

---

## Updated Pricing

**Total for Confirmed Services:** **{{ number_format($acceptedServices->sum('subtotal'), 2) }} TND**

@if($rejectedServices->count() > 0)
**Services Removed:** {{ number_format($rejectedServices->sum('subtotal'), 2) }} TND
@endif

---

## Next Steps

1. Review the modified reservation details
2. Contact the center if you have questions about unavailable services
3. Proceed with the confirmed services or cancel if needed

@component('mail::button', ['url' => $frontendUrl . '/reservations/' . $reservation->id, 'color' => 'primary'])
View Complete Reservation
@endcomponent

---

## Need Help?

Contact TunisiaCamp support at [{{ $supportEmail }}](mailto:{{ $supportEmail }})

@endcomponent