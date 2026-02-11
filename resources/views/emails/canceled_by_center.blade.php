@component('mail::message')
# 🏕️ Reservation Canceled by Center

Dear {{ $userName }},

We regret to inform you that your reservation has been canceled by the center. Please review the details below.

**Cancellation Reference:** #{{ str_pad($reservationId, 6, '0', STR_PAD_LEFT) }}  
**Cancellation Date:** {{ $canceledAt }}

---

## Cancellation Details

### Center Information
**Center Name:** {{ $centerName }}  
**Contact Email:** {{ $centerEmail }}  
**Phone:** {{ $centerPhone }}

### Reservation Details
**Dates:** {{ \Carbon\Carbon::parse($startDate)->format('F j, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('F j, Y') }}  
**Total Amount:** **{{ number_format($totalPrice, 2) }} TND**

---

## ⚠️ Important Refund Information

**According to our platform's refund policy:**

1. **Full Refund:** You will receive a **100% refund** for this cancellation
2. **Processing Time:** Refunds are typically processed within **5-7 business days**
3. **Payment Method:** The refund will be issued to your original payment method
4. **Notification:** You will receive a separate email once the refund is processed

**Estimated Refund Amount:** **{{ number_format($totalPrice, 2) }} TND** (Full Amount)

**⚠️ Please Note:** While you're receiving a full refund for this center-initiated cancellation, if you had initiated the cancellation, the refund policy would have been different with applicable fees.

---

## Alternative Options

We understand this is disappointing. Here are some alternative centers you might consider:

@component('mail::table')
| Center Name | Location | Price Range |
|-------------|----------|-------------|
@foreach($alternativeCenters as $center)
| **{{ $center['name'] }}** | {{ $center['location'] }} | {{ $center['price_range'] }} |
@endforeach
@endcomponent

@component('mail::button', ['url' => config('app.frontend_url') . '/zones', 'color' => 'success'])
Explore Other Centers
@endcomponent

---

## Need Assistance?

### Contact the Center
**Email:** [{{ $centerEmail }}](mailto:{{ $centerEmail }})  
**Phone:** {{ $centerPhone }}

### TunisiaCamp Support
**Email:** support@tunisiacamp.com  
**Hours:** 9 AM - 6 PM, Monday to Friday

@component('mail::button', ['url' => config('app.frontend_url') . '/help', 'color' => 'info'])
Get Help
@endcomponent

---

We apologize for any inconvenience this may have caused and hope to assist you with finding alternative accommodations.

Sincerely,

**TunisiaCamp Customer Support**

@component('mail::subcopy')
This is an automated message. For refund inquiries, contact: support@tunisiacamp.com  
Cancellation ID: {{ $reservationId }} · Processed: {{ now()->format('Y-m-d H:i') }}
@endcomponent
@endcomponent