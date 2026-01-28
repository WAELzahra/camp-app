@component('mail::message')
# Password Reset Request

Hello {{ $user?->first_name ?? 'there' }},

Use this verification code to reset your TunisiaCamp password:

@component('mail::panel')
<div style="
    text-align: center;
    font-size: 32px;
    font-weight: 600;
    letter-spacing: 8px;
    color: #0E3D38;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
">
    {{ $code }}
</div>

<p style="
    text-align: center;
    font-size: 13px;
    color: #6b7280;
    margin-top: 8px;
">
    Code expires in {{ $expiresAt->diffForHumans(null, true) }}
</p>
@endcomponent

Go to: [{{ $frontendUrl }}]({{ $frontendUrl }})

---

If you didn't request this, please ignore this email.

Kind regards,  
**TunisiaCamp Team**

@component('mail::subcopy')
Expires: {{ $expiresAt->format('F j, Y \\a\\t g:i A') }}
@endcomponent
@endcomponent