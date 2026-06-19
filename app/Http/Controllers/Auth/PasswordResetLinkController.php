<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset link request for API.
     */
    public function store(PasswordResetLinkRequest $request): \Illuminate\Http\JsonResponse
    {
        $request->validated();

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Email envoyé. Vérifie ta boîte de réception.'])
            : response()->json(['message' => 'Impossible d’envoyer l’email.'], 422);
    }
}
