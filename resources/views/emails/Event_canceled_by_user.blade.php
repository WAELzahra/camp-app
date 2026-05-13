@component('mail::message')
# Reservation Canceled

Dear {{ $userName }},

Your reservation for **{{ $eventTitle }}** has been successfully canceled. Please review the details below.

**Cancellation Reference:** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}
**Canceled At:** {{ $canceledAt }}

---

## Reservation Summary

**Event:** {{ $eventTitle }}
**Dates:** {{ \Carbon\Carbon::parse($eventStartDate)->format('F j, Y') }} – {{ \Carbon\Carbon::parse($eventEndDate)->format('F j, Y') }}
**Spots Reserved:** {{ $nbrPlace }}
**Total Paid:** **{{ number_format($totalPrice, 2) }} TND**

---

## Refund Breakdown

| Description | Amount |
|---|---|
| Original Payment | {{ number_format($totalPrice, 2) }} TND |
| Cancellation Fee ({{ $cancellationFeePercent }}%) | - {{ number_format($cancellationFee, 2) }} TND |
| Processing Fee ({{ $processingFeePercent }}%) | - {{ number_format($processingFee, 2) }} TND |
| **Estimated Refund** | **{{ number_format($refundAmount, 2) }} TND** |

> ⚠️ Refund amounts are estimates and will be processed within **7–14 business days** to your original payment method.

---

## What Happens Next?

1. Our team will review your cancellation request
2. A refund of approximately **{{ number_format($refundAmount, 2) }} TND** will be initiated
3. You'll receive a separate confirmation once the refund is processed

If you have any questions, please contact us at **support@tunisiacamp.com**.

@component('mail::button', ['url' => config('app.frontend_url') . '/events', 'color' => 'success'])
Browse Other Events
@endcomponent

Sincerely,

**TunisiaCamp Team**

@component('mail::subcopy')
This is an automated message. Do not reply directly to this email.
For support: support@tunisiacamp.com · Cancellation ID: #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }} · {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent