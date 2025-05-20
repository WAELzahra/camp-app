<?php

// namespace App\Http\Controllers\Auth;

// use App\Http\Controllers\Controller;
// use App\Http\Requests\Auth\LoginRequest;
// use App\Providers\RouteServiceProvider;
// use Illuminate\Http\RedirectResponse;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\View\View;

// class AuthenticatedSessionController extends Controller
// {
//     /**
//      * Display the login view.
//      */
//     public function create(): View
//     {
//         return view('auth.login');
//     }

 


// public function store(LoginRequest $request): RedirectResponse
// {
//     $credentials = $request->only('email', 'password');
//     $remember = $request->boolean('remember');

//     if (!Auth::attempt($credentials, $remember)) {
//         return back()->withErrors([
//             'email' => 'Les informations d’identification sont incorrectes.',
//         ])->withInput();
//     }

//     $user = Auth::user();

//     if ($user->is_active == 0) {
//         Auth::logout();
//         return back()->withErrors([
//             'email' => 'Votre compte est désactivé. Veuillez attendre l’activation par l’administrateur.',
//         ])->withInput();
//     }

//     $request->session()->regenerate();

//     return redirect()->intended(RouteServiceProvider::HOME);
// }




//     /**
//      * Destroy an authenticated session.
//      */
//     public function destroy(Request $request): RedirectResponse
//     {
//         Auth::guard('web')->logout();

//         $request->session()->invalidate();

//         $request->session()->regenerateToken();

//         return redirect('/');
//     }
    

// public function apiLogin(Request $request)
// {
//     $credentials = $request->validate([
//         'email' => ['required', 'email'],
//         'password' => ['required'],
//     ]);

//     if (!Auth::attempt($credentials)) {
//         return response()->json(['message' => 'Identifiants invalides.'], 401);
//     }

//     $user = Auth::user();

//     if ($user->is_active == 0) {
//         Auth::logout(); // Nettoyer la session si elle existe
//         return response()->json([
//             'message' => 'Votre compte est désactivé. Veuillez attendre l’activation par l’administrateur.'
//         ], 403);
//     }

//     $token = $user->createToken('api-token')->plainTextToken;

//     return response()->json([
//         'access_token' => $token,
//         'token_type' => 'Bearer',
//         'user' => $user,
//     ]);
// }


    
// }


namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Gère la connexion (web ou API).
     */
    public function store(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Vérifie si la requête attend une réponse JSON
        $expectsJson = $request->expectsJson() || $request->is('api/*');

        if (!Auth::attempt($credentials)) {
            if ($expectsJson) {
                return response()->json([
                    'message' => 'Identifiants invalides.'
                ], 401);
            }

            return back()->withErrors([
                'email' => 'Identifiants invalides.',
            ]);
        }

        $user = Auth::user();

        // Vérification de l’activation du compte
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
            // Génère un token pour les API
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ]);
        }

        // Redirige vers le tableau de bord pour une requête web
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
