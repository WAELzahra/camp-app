<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

class AuthenticatedSessionController extends Controller
{


    public function store(Request $request)
{
    $credentials = $request->only('email', 'password');
    $expectsJson = $request->expectsJson() || $request->is('api/*');

    if (!Auth::attempt($credentials)) {
        if ($expectsJson) {
            return response()->json([
                'message' => 'Erreur de connexion',
                'fields' => [
                    'email' => 'Email or password invalid',
                    'password' => 'Email or password invalid',
                ]
            ], 401);
        }

        return back()->withErrors([
            'email' => 'Identifiants invalides.',
        ]);
    }

    $user = Auth::user();

    if (!$user->is_active) {
        Auth::logout();

        if ($expectsJson) {
            return response()->json([
                'message' => 'Votre compte est désactivé. Veuillez attendre l’activation par l’administrateur.'
            ], 403);
        }

        return back()->withErrors([
            'email' => 'Votre compte est désactivé.',
        ]);
    }

    if ($expectsJson) {
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    return redirect()->intended('dashboard');
}


    /**
     * Déconnecte l'utilisateur (web).
     */
    public function destroy(Request $request)
{
    if ($request->user()) {
        $request->user()->currentAccessToken()->delete(); // supprime le token actuel
    }

    return response()->json([
        'message' => 'Déconnexion réussie.'
    ]);
}


    /**
     * (Optionnel) Connexion API dédiée — si tu veux garder une route API à part.
     */
    public function apiLogin(Request $request)
    {
        return $this->store($request);
    }
}
