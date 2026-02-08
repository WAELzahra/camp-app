@component('mail::message')
# Reservation Rejected

Hello {{ $user->first_name ?? 'Valued Customer' }},

We regret to inform you that your reservation has been **rejected**.

@if($reservation)
**Reservation Details:**  
**ID:** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}  
@if($reservation->date_debut)
**Dates:** {{ \Carbon\Carbon::parse($reservation->date_debut)->format('M j, Y') }} to {{ \Carbon\Carbon::parse($reservation->date_fin)->format('M j, Y') }}  
@endif
@if($reservation->nbr_place)
**Number of Guests:** {{ $reservation->nbr_place }}  
@endif
@if($reservation->total_price)
**Total:** {{ number_format($reservation->total_price, 2) }} TND  
@endif
@endif

@if($reason)
## Reason for Rejection
<div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 12px 16px; margin: 16px 0; border-radius: 4px;">
{{ $reason }}
</div>
@endif

## What You Can Do Next

1. **Modify Your Reservation** - You can edit your reservation and submit it again
2. **Choose Different Dates** - Try booking for alternative dates
3. **Contact Support** - If you have questions about this decision
4. **Explore Other Options** - Check availability at other centers

@component('mail::button', ['url' => $frontendUrl . '/reservations', 'color' => 'primary'])
View All Reservations
@endcomponent

@if($reservation)
@component('mail::button', ['url' => $frontendUrl . '/search', 'color' => 'success'])
Search Alternative Dates
@endcomponent
@endif

## Need Assistance?
If you need help understanding why your reservation was rejected or would like to discuss alternatives, please contact our support team.

📞 **Contact Support:** [{{ $supportEmail }}](mailto:{{ $supportEmail }})

---

**Best Regards,**  
The TunisiaCamp Team  

<div style="text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px;">
<p style="margin: 4px 0;">This is an automated notification. Please do not reply to this email.</p>
<p style="margin: 4px 0;">TunisiaCamp • Making Outdoor Experiences Accessible</p>
</div>
@endcomponent