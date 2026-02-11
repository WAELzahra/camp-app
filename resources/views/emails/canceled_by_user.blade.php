@component('mail::message')
# Reservation Canceled by User

Dear {{ $centerName }},

A user has canceled their reservation with your center. Please review the details below.

**Cancellation Reference:** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}  
**Cancellation Date:** {{ $canceledAt }}

---

## Cancellation Details

### User Information
**Name:** {{ $userName }}  
**Email:** {{ $userEmail }}

### Reservation Details
**Dates:** {{ \Carbon\Carbon::parse($startDate)->format('F j, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('F j, Y') }}  
**Total Value:** **{{ number_format($totalPrice, 2) }} TND**  
**Number of Services:** {{ $serviceCount }}  
**Note from User:** {{ $note ?? 'No additional notes provided' }}

---

## ⚠️ Important Refund Policy Note

According to our platform policies, when a user cancels a reservation:

1. **Administrative Fee:** A {{ config('app.cancellation_fee_percent', 15) }}% administrative fee is non-refundable
2. **Refund Timeline:** Refunds (if applicable) are processed within 7-14 business days
3. **Partial Refund:** Users may receive a partial refund depending on the cancellation timing
4. **Late Cancellations:** Cancellations within 48 hours of check-in may not be eligible for refund

**Estimated Refundable Amount:** **{{ number_format($totalPrice * 0.85, 2) }} TND**  
*(This is an estimate - actual amount may vary based on your specific center policy)*

---

## Next Steps Required

1. **Review Cancellation** – Verify the cancellation details
2. **Update Availability** – Mark the dates as available in your calendar
3. **Process Refund** – If applicable, initiate refund within 48 hours
4. **Update Records** – Mark the reservation as canceled in your system
@component('mail::button', ['url' => config('app.frontend_url') . '/center/reservations', 'color' => 'info'])
Manage Reservations
@endcomponent

---

## Contact Information

**User:** {{ $userName }} ({{ $userEmail }})  
**Platform Support:** support@tunisiacamp.com

---

Please ensure you communicate clearly with the user regarding any refunds or policy questions.

Sincerely,

**TunisiaCamp Cancellation Team**

@component('mail::subcopy')
This is an automated notification. Please do not reply directly to this email.  
For support, contact: support@tunisiacamp.com  
Cancellation ID: {{ $reservationId }} · Processed: {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent