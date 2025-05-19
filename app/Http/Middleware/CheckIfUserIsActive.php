<?php   
// app/Http/Middleware/CheckIfUserIsActive.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckIfUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->is_active == 0) {
            Auth::logout();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Votre compte est désactivé. Veuillez attendre l’activation par l’administrateur.'
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Votre compte est désactivé. Veuillez attendre l’activation par l’administrateur.',
            ]);
        }

        return $next($request);
    }
}
