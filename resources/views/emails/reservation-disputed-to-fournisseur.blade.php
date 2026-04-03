@component('mail::message')
# Overdue Rental Alert ⚠️

Hi **{{ $reservation->fournisseur->first_name ?? 'Supplier' }}**,

A camper has not returned your equipment past the agreed return date. The reservation has been automatically marked as **disputed**.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Item:** {{ $reservation->materielle->nom ?? 'N/A' }}
**Camper:** {{ ($reservation->user->first_name ?? '') . ' ' . ($reservation->user->last_name ?? '') }}
**Camper Email:** {{ $reservation->user->email ?? 'N/A' }}
**Return Deadline:** {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
**Status:** Disputed — overdue return
@endcomponent

Please contact the camper directly or reach out to TunisiaCamp support to resolve this matter.

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
