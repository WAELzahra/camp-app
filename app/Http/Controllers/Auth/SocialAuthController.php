<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        return $this->loginOrCreateUser($googleUser);
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        $facebookUser = Socialite::driver('facebook')->stateless()->user();
        return $this->loginOrCreateUser($facebookUser);
    }

    protected function loginOrCreateUser($providerUser)
    {
        // Vérifie si l'utilisateur existe déjà
        $user = User::where('email', $providerUser->getEmail())->first();

        if (!$user) {
            // Créer un nouvel utilisateur avec le rôle campeur (role_id = 3)
            $user = User::create([
                'name' => $providerUser->getName(),
                'email' => $providerUser->getEmail(),
                'password' => bcrypt(Str::random(16)),
                'role_id' => 3, // Campeur
            ]);
        }

        Auth::login($user);
        return redirect()->intended('/dashboard');
    }
}
