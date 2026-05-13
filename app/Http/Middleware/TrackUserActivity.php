<?php
// app/Http/Middleware/TrackUserActivity.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Update last activity timestamp
            // You can use a separate `last_activity` column or reuse `updated_at`
            $user->last_login_at = now();
            $user->save();
        }

        return $next($request);
    }
}