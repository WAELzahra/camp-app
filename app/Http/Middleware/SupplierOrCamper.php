<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SupplierOrCamper
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // Allow role_id 1 (campeur) or 4 (supplier)
        if ($user->role_id === 1 || $user->role_id === 4) {
            return $next($request);
        }
        
        return response()->json([
            'message' => 'Unauthorized. Only suppliers and campers can access this resource.'
        ], 403);
    }
}