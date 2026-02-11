@component('mail::message')
# ✅ Cancellation Confirmation

Dear {{ $userName }},

Your reservation cancellation has been successfully processed. Please keep this confirmation for your records.

**Cancellation Number:** {{ $cancellationNumber }}  
**Cancellation Date:** {{ $canceledAt }}

---

## Cancellation Summary

### Reservation Details
**Center:** {{ $centerName }}  
**Dates:** {{ \Carbon\Carbon::parse($startDate)->format('F j, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('F j, Y') }}  
**Original Amount:** **{{ number_format($totalPrice, 2) }} TND**  
**Reservation ID:** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}

---

## ⚠️ Important: Refund Policy Applied

Because **you initiated this cancellation**, the following refund policy applies:

### 📋 Standard Cancellation Policy:
1. **Administrative Fee:** {{ config('app.cancellation_fee_percent', 15) }}% non-refundable
2. **Processing Fee:** {{ config('app.processing_fee', 2) }}% payment processing fee
3. **Timing-Based Deductions:** Additional fees may apply based on cancellation timing

### 💰 Estimated Refund Calculation:
| Description | Amount |
|-------------|--------|
| Original Amount | {{ number_format($totalPrice, 2) }} TND |
| Administrative Fee ({{ config('app.cancellation_fee_percent', 15) }}%) | -{{ number_format($totalPrice * 0.15, 2) }} TND |
| Processing Fee ({{ config('app.processing_fee', 2) }}%) | -{{ number_format($totalPrice * 0.02, 2) }} TND |
| **Estimated Refund** | **{{ number_format($totalPrice * 0.83, 2) }} TND** |

**⚠️ Important Note:** If the center had canceled instead, you would have received a **100% full refund**. User-initiated cancellations incur fees as per our terms of service.

---

## Refund Timeline

1. **Processing:** 1-2 business days
2. **Bank Processing:** 5-10 business days (varies by bank)
3. **Total Timeline:** 7-14 business days for funds to appear in your account

You will receive a separate notification once your refund is processed.

---

## Need to Modify Instead of Cancel?

If you'd like to modify your reservation instead of canceling, you can:

Modify Reservation

---

## Contact Information

**Center:** {{ $centerName }}  
**TunisiaCamp Support:** support@tunisiacamp.com  
**Support Hours:** 9 AM - 6 PM, Monday to Friday

---

Thank you for using TunisiaCamp. We hope to serve you again in the future.

Sincerely,

**TunisiaCamp Refunds Department**

@component('mail::subcopy')
This is an automated confirmation. For refund inquiries: support@tunisiacamp.com  
Cancellation ID: {{ $cancellationNumber }} · Processed: {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent