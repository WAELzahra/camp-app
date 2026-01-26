@component('mail::layout')
{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<div style="text-align: center; background: linear-gradient(135deg, #0E3D38 0%, #1a6459 100%); padding: 20px; border-radius: 0 0 10px 10px;">
    <img src="{{ asset('images/logo-white.png') }}" alt="{{ config('app.name') }}" style="height: 40px; margin-bottom: 10px;">
    <h2 style="color: white; margin: 0; font-size: 20px;">Welcome to the Camping Community!</h2>
</div>
@endcomponent
@endslot

{{-- Body --}}
<div style="padding: 30px 20px; background: #f9f9f9; border-radius: 10px; margin: 20px 0;">
    <h1 style="color: #0E3D38; text-align: center; margin-bottom: 30px;">Welcome to CampConnect! 🌲</h1>
    
    <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <p style="color: #333; line-height: 1.6; margin-bottom: 20px;">
            Hello Adventurer! 👋
        </p>
        
        <p style="color: #333; line-height: 1.6; margin-bottom: 25px;">
            We're excited to have you join <strong>CampConnect</strong> - Tunisia's premier camping community! 
            Get ready to discover amazing camping spots, connect with fellow adventurers, and create unforgettable memories.
        </p>
        
        <div style="text-align: center; margin: 30px 0;">
            <div style="display: inline-block; background: #0E3D38; color: white; padding: 10px 20px; border-radius: 5px; margin-bottom: 10px;">
                <span style="font-size: 14px;">One last step to get started:</span>
            </div>
        </div>
        
        @component('mail::button', ['url' => $verificationLink, 'color' => 'success'])
        <div style="display: flex; align-items: center; justify-content: center;">
            <span style="margin-right: 8px;">✅</span>
            <span>Verify My Email Address</span>
        </div>
        @endcomponent
        
        <p style="color: #666; font-size: 12px; text-align: center; margin-top: 15px;">
            Click the button above to verify your email address. This link expires in 15 minutes.
        </p>
    </div>
    
    <div style="margin-top: 30px; background: white; padding: 20px; border-radius: 10px; border-left: 4px solid #0E3D38;">
        <h3 style="color: #0E3D38; margin-top: 0;">What you can do with CampConnect:</h3>
        <ul style="color: #333; line-height: 1.8; padding-left: 20px;">
            <li>📍 <strong>Discover</strong> camping spots across Tunisia</li>
            <li>🏕️ <strong>Book</strong> campsites and services</li>
            <li>👥 <strong>Connect</strong> with other campers and groups</li>
            <li>🗺️ <strong>Plan</strong> camping trips with friends</li>
            <li>⭐ <strong>Share</strong> your experiences and reviews</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <p style="color: #666; font-size: 14px;">
            Need help? <a href="{{ config('app.url') }}/help" style="color: #0E3D38; text-decoration: none;">Visit our Help Center</a><br>
            Or email us at: <a href="mailto:support@campconnect.tn" style="color: #0E3D38;">support@campconnect.tn</a>
        </p>
    </div>
</div>

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
<div style="text-align: center; padding: 20px; background: #f5f5f5; border-radius: 10px 10px 0 0;">
    <div style="margin-bottom: 15px;">
        <a href="https://facebook.com/campconnect" style="margin: 0 10px; display: inline-block;">
            <img src="{{ asset('images/social/facebook.png') }}" alt="Facebook" style="width: 24px; height: 24px;">
        </a>
        <a href="https://instagram.com/campconnect" style="margin: 0 10px; display: inline-block;">
            <img src="{{ asset('images/social/instagram.png') }}" alt="Instagram" style="width: 24px; height: 24px;">
        </a>
        <a href="https://twitter.com/campconnect" style="margin: 0 10px; display: inline-block;">
            <img src="{{ asset('images/social/twitter.png') }}" alt="Twitter" style="width: 24px; height: 24px;">
        </a>
    </div>
    
    <p style="color: #666; font-size: 12px; margin: 5px 0;">
        © {{ date('Y') }} CampConnect. All rights reserved.
    </p>
    <p style="color: #666; font-size: 12px; margin: 5px 0;">
        You're receiving this email because you signed up for CampConnect.
    </p>
    <p style="color: #666; font-size: 12px; margin: 10px 0;">
        <a href="{{ config('app.url') }}/unsubscribe" style="color: #999; text-decoration: none;">Unsubscribe</a> | 
        <a href="{{ config('app.url') }}/privacy" style="color: #999; text-decoration: none;">Privacy Policy</a> | 
        <a href="{{ config('app.url') }}/terms" style="color: #999; text-decoration: none;">Terms</a>
    </p>
    <p style="color: #999; font-size: 11px; margin: 15px 0 0 0;">
        CampConnect · Adventure awaits · Tunis, Tunisia
    </p>
</div>
@endcomponent
@endslot
@endcomponent