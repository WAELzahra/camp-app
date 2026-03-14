@component('mail::message')
# Reservation Canceled 🚫

Hi,

A reservation has been **canceled** on your Tunisia Camp shop.

@component('mail::panel')
**Item:** {{ $reservation->materielle->nom ?? 'N/A' }}
**Type:** {{ $reservation->type_reservation === 'location' ? 'Rental' : 'Purchase' }}
**Quantity:** {{ $reservation->quantite }}
**Amount:** {{ number_format($reservation->montant_total, 2) }} TND
@if($reservation->type_reservation === 'location')
**Period:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
@endif
@endcomponent

Stock has been automatically restored.

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'primary'])
View My Reservations
@endcomponent

Best regards,
**The Tunisia Camp Team**
@endcomponent