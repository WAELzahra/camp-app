<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Pour les requêtes API, on ne redirige PAS du tout
        // On retourne null pour que Laravel lance une exception AuthenticationException
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }
        
        // Pour les requêtes web seulement
        return route('login');
    }
}