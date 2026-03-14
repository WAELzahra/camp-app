@component('mail::message')
# Reservation Rejected ❌

Hi **{{ $camper->first_name ?? 'Customer' }}**,

We regret to inform you that your reservation request has been **rejected** by the supplier.

@if($reason)
@component('mail::panel')
**Reason:** {{ $reason }}
@endcomponent
@endif

You can browse other available equipment on Tunisia Camp and submit a new request.

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173'), 'color' => 'primary'])
Browse Equipment
@endcomponent

Best regards,
**The Tunisia Camp Team**
@endcomponent