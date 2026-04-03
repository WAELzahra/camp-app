@component('mail::message')
# Modification Declined

Hi **{{ $reservation->centre->name ?? 'Centre' }}**,

**{{ $camperName }}** has reviewed your proposed changes to their reservation and chose to decline them.

The reservation has been returned to **Pending** status — you can review it again and either approve it as originally submitted or reach out to discuss alternatives.

@component('mail::panel')
**Reservation ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Check-in:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('d/m/Y') }}
**Check-out:** {{ \Carbon\Carbon::parse($reservation->date_fin)->format('d/m/Y') }}
**Guests:** {{ $reservation->nbr_place }}
**Current Status:** Pending (awaiting your action)
@endcomponent

@component('mail::button', ['url' => $frontendUrl . '/settings/reservations', 'color' => 'primary'])
View Reservations
@endcomponent

Best regards,
**The TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Reservation ID: {{ $reservation->id }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent
