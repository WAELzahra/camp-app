@component('mail::message')
# Welcome to TunisiaCamp

Hello {{ $user?->first_name ?? 'there' }},

Thank you for joining **TunisiaCamp**, a platform dedicated to discovering camping destinations, outdoor guides, and nature experiences across Tunisia.

To activate your account, please verify your email address using one of the methods below.

---

## Verify your email

### Option 1 — Verification code (recommended)

Enter the following **6-digit verification code** on the TunisiaCamp website:

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

Go to the verification page:  
[{{ $frontendUrl }}]({{ $frontendUrl }})

---

### Option 2 — Secure verification link

@component('mail::button', ['url' => $link, 'color' => 'primary'])
Verify Email Address
@endcomponent

This link will expire in **{{ $expiresAt->diffForHumans(null, true) }}**.

---

## What you can do on TunisiaCamp

- Discover camping locations across Tunisia  
- Connect with experienced guides and camping groups  
- Plan and manage your outdoor trips  
- Reserve camping services and equipment  

---

## Need help?

If you did not create an account, no action is required.

For assistance, contact us at  
[{{ $supportEmail }}](mailto:{{ $supportEmail }})

---

Kind regards,  
**The TunisiaCamp Team**

@component('mail::subcopy')
This verification will expire on {{ $expiresAt->format('F j, Y \\a\\t g:i A') }}.
@endcomponent
@endcomponent
