<?php

// namespace App\Http\Controllers\Auth;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Http\RedirectResponse;
// use Illuminate\Support\Facades\Log;

// class AuthenticatedSessionController extends Controller
// {
//     /**
//      * Connexion API avec token Sanctum
//      */
//     public function store(Request $request)
//     {
//         $credentials = $request->only('email', 'password');

//         if (!Auth::attempt($credentials)) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Invalid credentials'
//             ], 401);
//         }

//         $user = Auth::user();

//         $user->last_login_at = now();
//         $user->save();

//         // ✅ CORRECTION: Générer un vrai token Sanctum
//         $token = $user->createToken('auth_token')->plainTextToken;

//         return response()->json([
//             'success' => true,
//             'token' => $token,
//             'user' => $user
//         ]);
//     }

//     /**
//      * Déconnecte l'utilisateur (web et API)
//      */
//     public function destroy(Request $request)
//     {
//         // Supprimer le token API si présent
//         if ($request->user()) {
//             $request->user()->currentAccessToken()->delete();
//         }
        
//         // For session-based authentication (with cookies)
//         Auth::guard('web')->logout();
        
//         $request->session()->invalidate();
//         $request->session()->regenerateToken();
        
//         return response()->json([
//             'success' => true,
//             'message' => 'Déconnexion réussie.'
//         ]);
//     }

//     /**
//      * Connexion API dédiée (optionnelle)
//      */
//     public function apiLogin(Request $request)
//     {
//         return $this->store($request);
//     }
// }



namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
   public function store(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // Regenerate session ID on every login — prevents session fixation attacks
    // and guarantees a clean session even if the previous user's session
    // was not fully invalidated before this login attempt.
    $request->session()->regenerate();

    $user = Auth::user();
    $user->last_login_at = now();
    $user->save();

    return response()->json([
        'user' => $user
    ]);
}





    /**
     * Déconnecte l'utilisateur (web).
     */
    public function destroy(Request $request)
    {
        $user = Auth::user();
        
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