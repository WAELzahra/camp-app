<?php

// namespace App\Providers;

// // use Illuminate\Support\Facades\Gate;
// use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
// use Illuminate\Support\Facades\Gate;

// class AuthServiceProvider extends ServiceProvider

// {
//     /**
//      * The model to policy mappings for the application.
//      *
//      * @var array<class-string, class-string>
//      */
//     protected $policies = [
//         //
//     ];

//     /**
//      * Register any authentication / authorization services.
//      */
//     public function boot()
// {
//     $this->registerPolicies();

//     Gate::define('isAdmin', function ($user) {
//         return $user->role && $user->role->name === 'admin';
//     });
// }
// }


namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Notifications\ResetPassword;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot()
    {
        $this->registerPolicies();

        // Définition de la Gate pour l'admin
        Gate::define('isAdmin', function ($user) {
            return $user->role && $user->role->name === 'admin';
        });

        // Surcharge de l'URL de réinitialisation pour les APIs (ex: React)
        // Remplace https://ton-front-app.com/reset-password par l’URL de ton composant React de réinitialisation de mot de passe.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return 'https://ton-front-app.com/reset-password?token=' . $token . '&email=' . urlencode($notifiable->email);
        });
    }
}
