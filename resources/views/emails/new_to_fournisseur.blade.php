@component('mail::message')
# New Reservation Received 🛎️

Hi **{{ $camper->first_name ?? 'Supplier' }}**,

You have received a **new reservation request** on Tunisia Camp.

@component('mail::panel')
**Customer:** {{ $camper->first_name }} {{ $camper->last_name }}
**Email:** {{ $camper->email }}
**Item:** {{ $reservation->materielle->nom ?? 'N/A' }}
**Type:** {{ $reservation->type_reservation === 'location' ? 'Rental' : 'Purchase' }}
**Quantity:** {{ $reservation->quantite }}
**Amount:** {{ number_format($reservation->montant_total, 2) }} TND
@if($reservation->type_reservation === 'location')
**Period:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
@endif
**Delivery:** {{ $reservation->mode_livraison === 'delivery' ? 'Home Delivery' : 'Personal Pickup' }}
@if($reservation->adresse_livraison)
**Address:** {{ $reservation->adresse_livraison }}
@endif
@endcomponent

Log in to your supplier dashboard to **confirm or reject** this reservation.

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'primary'])
Manage My Reservations
@endcomponent

Best regards,
**The Tunisia Camp Team**
@endcomponent