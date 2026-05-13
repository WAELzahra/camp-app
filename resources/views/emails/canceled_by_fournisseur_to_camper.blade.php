@component('mail::message')
# Your Reservation Was Canceled ❌

Hi **{{ $reservation->user->first_name ?? 'Customer' }}**,

Unfortunately, the supplier has **canceled your reservation**.

@component('mail::panel')
**Item:** {{ $reservation->materielle->nom ?? 'N/A' }}
**Type:** {{ $reservation->type_reservation === 'location' ? 'Rental' : 'Purchase' }}
**Quantity:** {{ $reservation->quantite }}
**Amount:** {{ number_format($reservation->montant_total, 2) }} TND
@if($reservation->type_reservation === 'location')
**Period:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
@endif
@endcomponent

We apologize for the inconvenience. You can browse other available equipment on Tunisia Camp and submit a new request.

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'primary'])
Browse Equipment
@endcomponent

Best regards,
**The Tunisia Camp Team**
@endcomponent