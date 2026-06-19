<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteSocialRegistrationRequest;
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
            Log::error('Google OAuth error: '.$e->getMessage());
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            return redirect($frontendUrl.'/login?error='.urlencode('Google authentication failed'));
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
            Log::error('Facebook OAuth error: '.$e->getMessage());
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            return redirect($frontendUrl.'/login?error='.urlencode('Facebook authentication failed'));
        }
    }

    protected function loginOrCreateUser($providerUser, string $provider)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        $user = User::where('email', $providerUser->getEmail())->first();
        $isNewUser = false;

        if (!$user) {
            $nameParts = explode(' ', trim($providerUser->getName() ?? ''), 2);

            $user = User::create([
                'first_name' => $nameParts[0] ?? 'User',
                'last_name' => $nameParts[1] ?? '',
                'email' => $providerUser->getEmail(),
                'password' => bcrypt(Str::random(24)),
                'role_id' => 1, // campeur by default — completeRegistration() sets the real role
                'provider' => $provider,
                'provider_id' => $providerUser->getId(),
                'email_verified_at' => now(),
                // Always start INACTIVE. completeRegistration() activates campeurs (role_id=1)
                // and leaves all other roles inactive pending admin approval.
                // This prevents centre / supplier / guide accounts from being immediately
                // active simply because they used social login.
                'is_active' => false,
                'first_login' => true,
                'nombre_signalement' => 0,
            ]);

            // Create the base profile so downstream queries don't hit null-profile errors.
            \DB::table('profiles')->insert([
                'user_id' => $user->id,
                'type' => 'campeur',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $isNewUser = true;

            Log::info('New social user created (inactive until role confirmed)', [
                'user_id' => $user->id,
                'email' => $user->email,
                'provider' => $provider,
            ]);
        } else {
            // Existing user — just merge provider credentials. Never set isNewUser = true here.
            // The role-selection popup must only appear for brand-new social sign-ups.
            $user->update([
                'provider' => $user->provider ?? $provider,
                'provider_id' => $user->provider_id ?? $providerUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        }

        $user->load('role');
        $token = $user->createToken('social-auth')->plainTextToken;

        $params = http_build_query([
            'token' => $token,
            'is_new_user' => $isNewUser ? '1' : '0',
            'uuid' => $user->uuid,
            'first_name' => $user->first_name,
            'role' => $user->role?->name ?? 'campeur',
        ]);

        return redirect($frontendUrl.'/social-callback?'.$params);
    }

    /**
     * Called by the frontend after the role selection popup.
     * Updates the role for a newly created social user.
     */
    public function completeRegistration(CompleteSocialRegistrationRequest $request)
    {
        $data = $request->validated();

        $user = $request->user();

        if (!$user->provider) {
            return response()->json([
                'success' => false,
                'message' => 'Not a social auth account.',
            ], 403);
        }

        // Campeurs (role_id=1) are activated immediately; all other roles wait for admin approval.
        $isActive = $data['role_id'] === 1;

        $user->update([
            'role_id' => $data['role_id'],
            'is_active' => $isActive,
            'first_login' => false, // Role confirmed — don't re-show the popup on next login.
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $isActive,
            'user' => new \App\Http\Resources\UserResource($user->fresh()->load('role')),
        ]);
    }
}
