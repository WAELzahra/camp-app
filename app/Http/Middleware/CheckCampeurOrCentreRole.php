<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckCampeurOrCentreRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Allow both campeur (role_id = 1) and centre (role_id = 3)
        if ($user->role_id == 1 || $user->role_id == 3) {
            return $next($request);
        }
        
        return response()->json(['message' => 'Unauthorized. Only campers and centres can access this resource.'], 403);
    }
}