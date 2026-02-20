<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Profile;

class RegisteredUserController extends Controller
{
    /**
     * Affiche la vue d'inscription avec les rôles.
     */
    public function create(): \Illuminate\View\View
    {
        $roles = Role::where('name', '!=', 'admin')->get();
        return view('auth.register', compact('roles'));
    }

    /**
     * Enregistre un nouvel utilisateur
     */
    public function store(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'sexe' => ['nullable', 'string', 'max:10'],
            'langue' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', 'exists:roles,name'],
            
            // Make all these fields nullable instead of required
            'adresse' => ['nullable', 'string', 'max:500'],
            'capacite' => ['nullable', 'integer', 'min:1'],
            'services_offerts' => ['nullable', 'string'],
            'price_per_night' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'nom_groupe' => ['nullable', 'string', 'max:255'],
            'cin_responsable' => ['nullable', 'string', 'max:50'],
            'experience' => ['nullable', 'integer', 'min:0'],
            'tarif' => ['nullable', 'numeric', 'min:0'],
            'zone_travail' => ['nullable', 'string', 'max:255'],
            'cin' => ['nullable', 'string', 'max:50'],
            'cin_fournisseur' => ['nullable', 'string', 'max:50'],
            'interval_prix' => ['nullable', 'string', 'max:100'],
            'product_category' => ['nullable', 'string', 'max:255'],
            'legal_document_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'center_images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
        ]);

        if (strtolower($validated['role']) === 'admin') {
            return response()->json(['error' => 'Ce rôle n\'est pas autorisé à l\'inscription.'], 403);
        }

        $role = Role::where('name', $validated['role'])->first();
        if (!$role) return response()->json(['error' => 'Rôle invalide.'], 422);

        $isActive = $role->name === 'campeur' ? 1 : 0;

        DB::beginTransaction();
        try {
            // 1️⃣ Créer l'utilisateur
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
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

            // 2️⃣ Créer le profile général
            $profileId = DB::table('profiles')->insertGetId([
                'user_id' => $user->id,
                'bio' => null,
                'cover_image' => null,
                'type' => $role->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => $isActive
                    ? 'Inscription réussie ! Vous pouvez maintenant vous connecter.'
                    : 'Inscription réussie ! Veuillez attendre l\'activation par un administrateur.',
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'Une erreur est survenue lors de l\'inscription.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Les fonctions handleCenterServices, handleCenterEquipment et handleCenterImages restent identiques
}
