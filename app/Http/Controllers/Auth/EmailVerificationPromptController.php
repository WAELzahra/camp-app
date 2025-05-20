<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            // Si email vérifié, retourne statut succès JSON (ou message, ou URL front)
            return response()->json([
                'verified' => true,
                'redirect_to' => RouteServiceProvider::HOME,
            ]);
        } else {
            // Sinon, demande de vérification d'email
            return response()->json([
                'verified' => false,
                'message' => 'Veuillez vérifier votre adresse email.',
            ], 401); // 401 Unauthorized ou 403 Forbidden selon cas
        }
    }
}

