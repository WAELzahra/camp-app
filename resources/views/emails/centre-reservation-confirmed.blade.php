@component('mail::message')
# Centre Reservation Confirmation

Dear {{ $reservation->user->first_name ?? ($reservation->user->name ?? 'Valued Customer') }},

Your reservation has been successfully confirmed. We appreciate your trust in TunisiaCamp and look forward to hosting you for an exceptional camping experience.

**Reservation Reference:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}  
**Confirmation Date:** {{ now()->format('F j, Y') }}

---

## Reservation Overview

### Centre Information
**Centre Name:** {{ $reservation->centre->name ?? 'Camping Centre' }}  
**Location:** {{ optional(optional($reservation->centre->profile)->profileCentre)->adresse ?? 'Tunisia' }}  
**Booking Dates:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('F j, Y') }} to {{ \Carbon\Carbon::parse($reservation->date_fin)->format('F j, Y') }}  
**Duration:** {{ \Carbon\Carbon::parse($reservation->date_debut)->diffInDays($reservation->date_fin) + 1 }} nights  
**Number of Guests:** {{ $reservation->nbr_place }} person(s)

@if($reservation->type)
**Service Package:** {{ $reservation->type }}
@endif

@if($reservation->note)
**Additional Notes:** {{ $reservation->note }}
@endif

---

## Financial Summary

**Total Reservation Value:** **{{ number_format($reservation->total_price, 2) }} TND**

@if($reservation->serviceItems && $reservation->serviceItems->count() > 0)
### Detailed Service Breakdown

@component('mail::table')
| Service Description | Quantity | Unit Price | Subtotal |
|---------------------|----------|------------|----------|
@foreach($reservation->serviceItems as $item)
| **{{ $item->service_name }}** | {{ $item->quantity }} {{ $item->unit }} | {{ number_format($item->unit_price, 2) }} TND | {{ number_format($item->subtotal, 2) }} TND |
@if($item->service_description)
| <small>{{ $item->service_description }}</small> | | | |
@endif
@endforeach
| | | **Total Amount** | **{{ number_format($reservation->total_price, 2) }} TND** |
@endcomponent
@endif

---

## Important Information

### Arrival & Departure
- **Check-in Time:** From {{ $checkInTime }} (2:00 PM)
- **Check-out Time:** Before {{ $checkOutTime }} (11:00 AM)
- **Early check-in/late check-out:** Subject to availability and additional charges

### Centre Contact Details
- **Email:** {{ $reservation->centre->email ?? 'Will be provided separately' }}
- **Phone:** {{ optional($reservation->centre->profile)->profileCentre->contact_phone ?? $reservation->centre->phone_number ?? 'Available in centre information' }}
- **Address:** {{ optional(optional($reservation->centre->profile)->profileCentre)->adresse ?? 'Provided upon request' }}

### Required Documentation
- Valid government-issued photo identification
- Reservation confirmation (this email)
- Any required deposit or payment confirmation

---

## Next Steps

1. **Review Confirmation** – Verify all details match your expectations
2. **Save Documentation** – Keep this email for reference during your stay
3. **Plan Your Journey** – Consider travel time and local transportation
4. **Contact for Queries** – Reach out with any questions before arrival

@component('mail::button', ['url' => $reservationDetailsUrl, 'color' => 'primary'])
Access Complete Reservation Details
@endcomponent

---

## Assistance & Support

### For Reservation Inquiries
**Centre Management:** {{ $reservation->centre->email ?? 'Contact via TunisiaCamp platform' }}

### For Technical Support
**TunisiaCamp Customer Service:** [{{ $supportEmail }}](mailto:{{ $supportEmail }})  
**Support Hours:** Monday to Friday, 9:00 AM to 5:00 PM (Tunisia Time)  
**Online Help Center:** [{{ $frontendUrl }}/help]({{ $frontendUrl }}/help)

---

We wish you a memorable and enjoyable camping experience in Tunisia.

Sincerely,

**TunisiaCamp Reservations Team**

@component('mail::subcopy')
This is an automated confirmation message. Please do not reply directly to this email.  
For any inquiries, contact us at [{{ $supportEmail }}](mailto:{{ $supportEmail }}).  
Confirmation ID: {{ $reservation->id }} · Generated: {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent