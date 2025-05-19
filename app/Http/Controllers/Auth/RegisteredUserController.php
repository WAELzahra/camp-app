<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Affiche la vue d’inscription avec les rôles.
     */
    public function create(): \Illuminate\View\View
    {
        $roles = Role::where('name', '!=', 'admin')->get(); // Exclure le rôle admin
        return view('auth.register', compact('roles'));
    }

    /**
     * Gère l’inscription.
     */
    public function store(Request $request)
    {
        // Validation des champs
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'adresse' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'sexe' => ['nullable', 'string', 'max:10'],
            'langue' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        // Empêcher l'inscription avec le rôle "admin"
        if (strtolower($validated['role']) === 'admin') {
            return response()->json([
                'error' => 'Ce rôle n\'est pas autorisé à l\'inscription.'
            ], 403);
        }

        // Récupérer le rôle
        $role = Role::where('name', $validated['role'])->first();

        if (!$role) {
            return response()->json([
                'error' => 'Rôle invalide.'
            ], 422);
        }

        // Activer automatiquement si campeur
        $isActive = $role->name === 'campeur' ? 1 : 0;

        // Création de l'utilisateur
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'adresse' => $validated['adresse'],
            'phone_number' => $validated['phone_number'],
            'ville' => $validated['ville'] ?? null,
            'date_naissance' => $validated['date_naissance'] ?? null,
            'sexe' => $validated['sexe'] ?? null,
            'langue' => $validated['langue'] ?? null,
            'password' => Hash::make($validated['password']),
            'role_id' => $role->id,
            'is_active' => $isActive,
            'first_login' => true,
            'nombre_signalement' => 0,
        ]);

        // Message de retour
        $message = $isActive
            ? 'Inscription réussie ! Vous pouvez maintenant vous connecter.'
            : 'Inscription réussie ! Veuillez attendre l’activation par un administrateur.';

        return response()->json([
            'message' => $message,
            'user' => $user
        ], 201);
    }
}
