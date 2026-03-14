@component('mail::message')
# Reservation Confirmed ✅

Hi **{{ $reservation->user->first_name ?? 'Customer' }}**,

Your reservation has been **confirmed** by the supplier.

@component('mail::panel')
**Item:** {{ $reservation->materielle->nom ?? 'N/A' }}
**Type:** {{ $reservation->type_reservation === 'location' ? 'Rental' : 'Purchase' }}
**Quantity:** {{ $reservation->quantite }}
**Total Amount:** {{ number_format($reservation->montant_total, 2) }} TND
@if($reservation->type_reservation === 'location')
**Period:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
@endif
**Delivery:** {{ $reservation->mode_livraison === 'delivery' ? 'Home Delivery' : 'Personal Pickup' }}
@endcomponent

---

## Your PIN Code: `{{ $pin }}`

@component('mail::panel')
⚠️ **Important:** Present this PIN code to the supplier **when picking up your equipment**. Without this code, the handoff cannot be confirmed.

Keep this code confidential and do not share it with anyone other than the supplier.
@endcomponent

@if($reservation->type_reservation === 'location')
### Rental Policy Reminder

- The equipment must be returned before **{{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}**.
- Your national ID (CIN) has been recorded for this reservation as a legal guarantee.
- Any equipment not returned on time may be subject to legal action.
- Payment will only be released to the supplier after the return of the equipment is confirmed.
@else
### Purchase Policy Reminder

- The sale is final. No returns are accepted.
- Payment will be released to the supplier after delivery is confirmed via your PIN code.
@endif

Thank you for trusting **Tunisia Camp**!

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'success'])
View My Reservation
@endcomponent

Best regards,
**The Tunisia Camp Team**
@endcomponent