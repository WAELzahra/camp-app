@component('mail::layout')
{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<div style="text-align: center;">
    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" style="height: 50px; margin-bottom: 10px;">
    <h1 style="color: #0E3D38; margin: 0; font-size: 24px;">CampConnect</h1>
    <p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">Your camping community</p>
</div>
@endcomponent
@endslot

{{-- Body --}}
# Welcome to CampConnect! 🏕️

Hello Camper!

Thank you for joining CampConnect - your gateway to amazing camping experiences in Tunisia! To complete your registration and start exploring, please verify your email address using the code below:

@component('mail::panel', ['color' => '#0E3D38'])
<div style="text-align: center; padding: 20px;">
    <div style="font-size: 36px; font-weight: bold; letter-spacing: 10px; color: #0E3D38;">
        {{ $code }}
    </div>
    <div style="font-size: 14px; color: #666; margin-top: 10px;">
        Verification Code
    </div>
</div>
@endcomponent

**Important:** 
- This code will expire in 15 minutes
- Enter this code on the verification page
- If you didn't request this, please ignore this email

**What's next?** ✅
- Verify your email with the code above
- Complete your profile
- Start exploring camping spots
- Connect with other campers

@component('mail::button', ['url' => config('app.url') . '/verify-email', 'color' => 'success'])
Go to Verification Page
@endcomponent

Need help? Visit our [Help Center]({{ config('app.url') }}/help) or contact support at support@campconnect.tn

Happy Camping! 🏕️

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
<div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
    <p style="margin: 5px 0;">
        © {{ date('Y') }} CampConnect. All rights reserved.
    </p>
    <p style="margin: 5px 0;">
        This email was sent to {{ $user->email ?? 'you' }}. If you believe this was sent in error, please ignore it.
    </p>
    <p style="margin: 5px 0;">
        <a href="{{ config('app.url') }}/privacy" style="color: #0E3D38; text-decoration: none;">Privacy Policy</a> | 
        <a href="{{ config('app.url') }}/terms" style="color: #0E3D38; text-decoration: none;">Terms of Service</a>
    </p>
    <p style="margin: 10px 0 0 0;">
        CampConnect HQ · Tunis, Tunisia
    </p>
</div>
@endcomponent
@endslot
@endcomponent