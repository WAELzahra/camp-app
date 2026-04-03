@component('mail::message')
# Action Required: Overdue Equipment Return ⚠️

Hi **{{ $reservation->user->first_name ?? 'Customer' }}**,

Our records show that you have not returned the rented equipment past the agreed return date. Your reservation has been flagged as **disputed**.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Item:** {{ $reservation->materielle->nom ?? 'N/A' }}
**Supplier:** {{ ($reservation->fournisseur->first_name ?? '') . ' ' . ($reservation->fournisseur->last_name ?? '') }}
**Return Deadline:** {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
**Status:** Disputed — overdue return
@endcomponent

Please contact the supplier or TunisiaCamp support immediately to resolve this matter.

Failure to return the equipment may result in legal action in accordance with Tunisian law.

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'error'])
View My Reservations
@endcomponent

For assistance, contact us at [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Reservation ID: {{ $reservation->id }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent
