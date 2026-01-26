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

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['message' => 'Account inactive'], 403);
        }

        return response()->json([
            'user' => $user
        ]);
    }


    /**
     * Déconnecte l'utilisateur (web).
     */
    public function destroy(Request $request)
    {
        // For session-based authentication (with cookies)
        Auth::guard('web')->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
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
