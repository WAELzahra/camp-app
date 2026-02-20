<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'register',
        'login',
        'api/forgot-password',
        'api/verify-password',
        'api/reset-password',
        'api/send-verification',
        'resend-verification',
        'broadcasting/auth',
        // AJOUTEZ CES LIGNES POUR VOS ROUTES ADMIN
        'api/admin/*',           // Exclure toutes les routes admin API
        'api/admin/users/*',      // Exclure toutes les routes users
        'api/admin/users/*/photos', // Exclure spécifiquement l'upload de photos
        'api/*/photos',           // Exclure toutes les routes photos
        'sanctum/csrf-cookie',    // Route pour rafraîchir le cookie CSRF
    ];
}