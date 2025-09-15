<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanPublishAnnonce
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, \Closure $next)
    {
        if (!in_array(auth()->user()->role->name, ['fournisseur', 'centre', 'admin'])) {
            abort(403, "Seuls les fournisseurs et centres peuvent accéder.");
        }
        return $next($request);
        
    }
    
}
