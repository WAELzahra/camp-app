<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }
  

public function store(Request $request): JsonResponse
{
    // Auth::guard('web') remplacé par Auth::guard() par défaut (lié à Sanctum/token)
    if (! Auth::guard()->validate([
        'email' => $request->user()->email,
        'password' => $request->password,
    ])) {
        return response()->json([
            'message' => 'The password is incorrect.',
        ], 422);
    }

    $request->session()->put('auth.password_confirmed_at', time());

    return response()->json([
        'message' => 'Password confirmed successfully.'
    ], 200);
}

}
