<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            return $this->loginOrCreateUser($googleUser, 'google');
        } catch (\Exception $e) {
            Log::error('Google OAuth error: ' . $e->getMessage());
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return redirect($frontendUrl . '/login?error=' . urlencode('Google authentication failed'));
        }
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')
            ->scopes(['email', 'public_profile'])
            ->fields(['name', 'email', 'first_name', 'last_name'])
            ->stateless()
            ->redirect();
    }

    public function handleFacebookCallback(Request $request)
    {
        try {
            $facebookUser = Socialite::driver('facebook')
                ->fields(['name', 'email', 'first_name', 'last_name'])
                ->stateless()
                ->user();
            return $this->loginOrCreateUser($facebookUser, 'facebook');
        } catch (\Exception $e) {
            Log::error('Facebook OAuth error: ' . $e->getMessage());
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return redirect($frontendUrl . '/login?error=' . urlencode('Facebook authentication failed'));
        }
    }

    protected function loginOrCreateUser($providerUser, string $provider)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        $user = User::where('email', $providerUser->getEmail())->first();
        $isNewUser = false;

        if (!$user) {
            $nameParts = explode(' ', $providerUser->getName() ?? '', 2);

            $user = User::create([
                'first_name'        => $nameParts[0] ?? 'User',
                'last_name'         => $nameParts[1] ?? '',
                'email'             => $providerUser->getEmail(),
                'password'          => bcrypt(Str::random(24)),
                'role_id'           => 1, // campeur par défaut
                'provider'          => $provider,
                'provider_id'       => $providerUser->getId(),
                'email_verified_at' => now(),
                'is_active'         => true,
            ]);

            $isNewUser = true;

            Log::info('New social user created', [
                'user_id'  => $user->id,
                'email'    => $user->email,
                'provider' => $provider,
            ]);
        } else {
            $user->update([
                'provider'           => $user->provider ?? $provider,
                'provider_id'        => $user->provider_id ?? $providerUser->getId(),
                'email_verified_at'  => $user->email_verified_at ?? now(),
            ]);
        }

        $token = $user->createToken('social-auth')->plainTextToken;

        $params = http_build_query([
            'token'       => $token,
            'is_new_user' => $isNewUser ? '1' : '0',
            'user_id'     => $user->id,
            'first_name'  => $user->first_name,
            'role_id'     => $user->role_id,
        ]);

        return redirect($frontendUrl . '/social-callback?' . $params);
    }

    /**
     * Called by the frontend after the role selection popup.
     * Updates the role for a newly created social user.
     */
    public function completeRegistration(Request $request)
    {
        $data = $request->validate([
            'role_id' => 'required|integer|between:1,5',
        ]);

        $user = $request->user();

        if (!$user->provider) {
            return response()->json([
                'success' => false,
                'message' => 'Not a social auth account.',
            ], 403);
        }

        // Campeurs (role_id=1) sont activés immédiatement, les autres nécessitent validation
        $isActive = $data['role_id'] === 1;

        $user->update([
            'role_id'   => $data['role_id'],
            'is_active' => $isActive,
        ]);

        return response()->json([
            'success'   => true,
            'is_active' => $isActive,
            'user'      => $user->fresh(),
        ]);
    }
}
