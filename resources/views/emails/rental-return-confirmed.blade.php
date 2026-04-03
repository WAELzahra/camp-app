@component('mail::message')
# Equipment Return Confirmed ✅

Hi **{{ $reservation->user->first_name ?? 'Customer' }}**,

The supplier has confirmed the return of your rented equipment. Your rental is now complete.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Item:** {{ $reservation->materielle->nom ?? 'N/A' }}
**Supplier:** {{ ($reservation->fournisseur->first_name ?? '') . ' ' . ($reservation->fournisseur->last_name ?? '') }}
**Rental Period:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
**Quantity:** {{ $reservation->quantite }}
**Total Paid:** {{ number_format($reservation->montant_total, 2) }} TND
**Returned On:** {{ \Carbon\Carbon::parse($reservation->returned_at)->format('d/m/Y H:i') }}
@endcomponent

Thank you for using TunisiaCamp. We hope you enjoyed the equipment!

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'success'])
View My Reservations
@endcomponent

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Reservation ID: {{ $reservation->id }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent
