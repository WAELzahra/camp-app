<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\EmailVerificationService;

class SocialAuthController extends Controller
{
    protected $emailVerificationService;
    
    public function __construct()
    {
        $this->emailVerificationService = new EmailVerificationService();
    }
    
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            return $this->loginOrCreateUser($googleUser, 'google', $request);
        } catch (\Exception $e) {
            Log::error('Google OAuth error: ' . $e->getMessage());
            return redirect(config('app.frontend_url') . '/login?error=' . urlencode('Google authentication failed'));
        }
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback(Request $request)
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();
            return $this->loginOrCreateUser($facebookUser, 'facebook', $request);
        } catch (\Exception $e) {
            Log::error('Facebook OAuth error: ' . $e->getMessage());
            return redirect(config('app.frontend_url') . '/login?error=' . urlencode('Facebook authentication failed'));
        }
    }

    protected function loginOrCreateUser($providerUser, $provider, Request $request)
    {
        // Vérifie si l'utilisateur existe déjà
        $user = User::where('email', $providerUser->getEmail())->first();

        $isNewUser = false;
        
        if (!$user) {
            // Split name into first and last name
            $nameParts = explode(' ', $providerUser->getName(), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
            // Create new user with camper role (role_id = 3)
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $providerUser->getEmail(),
                'password' => bcrypt(Str::random(16)),
                'role_id' => 3, // Campeur
                'provider' => $provider,
                'provider_id' => $providerUser->getId(),
                'email_verified_at' => now(), // Social users are auto-verified
                'is_active' => true,
            ]);
            
            $isNewUser = true;
            
            Log::info('New user created via social auth', [
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => $provider
            ]);
        } else {
            // Update existing user with provider info if not set
            if (!$user->provider) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $providerUser->getId(),
                ]);
            }
        }

        // Create a Sanctum token for API authentication
        $token = $user->createToken('social-auth-token')->plainTextToken;

        // Get redirect URL from query parameter or use default
        $redirectUrl = $request->query('redirect', config('app.frontend_url'));
        
        // For API requests (from React frontend)
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => $isNewUser ? 'Account created successfully' : 'Logged in successfully',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'token' => $token,
                'is_new_user' => $isNewUser,
                'redirect_to' => $redirectUrl
            ]);
        }

        // For traditional web requests (fallback)
        Auth::login($user);
        return redirect()->intended('/dashboard');
    }
    
    /**
     * Get social auth URLs for frontend
     */
    public function getSocialAuthUrls(Request $request)
    {
        $redirectUrl = $request->query('redirect', config('app.frontend_url') . '/social-callback');
        
        return response()->json([
            'google' => route('google.redirect') . '?redirect=' . urlencode($redirectUrl),
            'facebook' => route('facebook.redirect') . '?redirect=' . urlencode($redirectUrl),
        ]);
    }
}