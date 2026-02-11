@component('mail::message')
# 📝 Cancellation Record Confirmation

Dear {{ $centerName }},

You have successfully canceled a user's reservation. This email confirms the action for your records.

**Cancellation Reference:** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}  
**Cancellation Date:** {{ $canceledAt }}

---

## Cancellation Record

### User Information
**Name:** {{ $userName }}  
**Email:** {{ $userEmail }}

### Reservation Details
**Dates:** {{ \Carbon\Carbon::parse($startDate)->format('F j, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('F j, Y') }}  
**Total Value:** **{{ number_format($totalPrice, 2) }} TND**  
**Vacant Spots Created:** {{ $vacantSpots }}

### Cancellation Reason
{{ $cancellationReason }}

---

## ⚠️ Important Financial Implications

**Because this cancellation was initiated by you (the center):**

### Financial Responsibility:
1. **Full Refund Required:** You must provide a **100% refund** to the user
2. **Refund Timeline:** Refund must be processed within **48 hours**
3. **Platform Fee:** Your platform commission fee for this booking is **waived**
4. **Penalty Protection:** No penalties apply for this cancellation

### Key Difference:
- **User cancels:** User pays cancellation fees (partial refund)
- **Center cancels:** Center provides full refund (no fees to user)

**⚠️ Action Required:** Please initiate the refund of **{{ number_format($totalPrice, 2) }} TND** to the user immediately.

---

## Next Steps for Your Center

1. **Process Refund** – Issue full refund to the user
2. **Update Availability** – Mark {{ $vacantSpots }} spot(s) as available
3. **Notify Staff** – Inform your team about the cancellation
4. **Review Policy** – Consider why this cancellation occurred

View Financial Dashboard

---

## User Communication

The user has been notified and expects:
- Full refund of {{ number_format($totalPrice, 2) }} TND
- Refund within 48 hours
- Clear communication from your center

**User Contact:** {{ $userName }} ({{ $userEmail }})

---

## Platform Support

If you need assistance with the refund process:

**TunisiaCamp Support:** support@tunisiacamp.com  
**Financial Department:** finance@tunisiacamp.com  
**Emergency Line:** +216 70 123 456

---

Please ensure all financial obligations are met to maintain your center's reputation and rating.

Sincerely,

**TunisiaCamp Center Relations**

@component('mail::subcopy')
This is an automated record. For financial inquiries: finance@tunisiacamp.com  
Record ID: {{ $reservationId }} · Created: {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent