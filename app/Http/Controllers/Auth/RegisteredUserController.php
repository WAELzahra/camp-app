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

    public function store(Request $request)
    {
        // Debug: Log incoming request
        \Log::info('Registration request received', [
            'data' => $request->all(),
            'center_data' => $request->input('center_data'),
        ]);

        // Validation de base
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
            
            // Champs spécifiques aux rôles
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
        ]);

        if (strtolower($validated['role']) === 'admin') {
            return response()->json(['error' => 'Ce rôle n\'est pas autorisé à l\'inscription.'], 403);
        }

        $role = Role::where('name', $validated['role'])->first();

        if (!$role) {
            return response()->json(['error' => 'Rôle invalide.'], 422);
        }

        $isActive = $role->name === 'campeur' ? 1 : 0;

        // Démarrer une transaction pour garantir l'intégrité des données
        DB::beginTransaction();

        try {
            // Création utilisateur
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

            // Création profile mère
            $profileId = DB::table('profiles')->insertGetId([
                'user_id' => $user->id,
                'bio' => null,
                'cover_image' => null,
                'type' => $role->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Création profile enfant selon le type
            switch ($role->name) {
                case 'guide':
                    DB::table('profile_guides')->insert([
                        'profile_id' => $profileId,
                        'adresse' => $request->input('adresse') ?? null,
                        'cin' => $request->input('cin') ?? $request->input('cin_guide') ?? null,
                        'experience' => $request->input('experience') ?? null,
                        'tarif' => $request->input('tarif') ?? null,
                        'zone_travail' => $request->input('zone_travail') ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;

                case 'centre':
                    // Créer le profil centre
                    $centreId = DB::table('profile_centres')->insertGetId([
                        'profile_id' => $profileId,
                        'name' => $request->input('name') ?? null,
                        'adresse' => $request->input('adresse') ?? null,
                        'contact_email' => $request->input('contact_email') ?? null,
                        'contact_phone' => $request->input('contact_phone') ?? null,
                        'manager_name' => $request->input('manager_name') ?? null,
                        'capacite' => $request->input('capacite') ?? null,
                        'price_per_night' => $request->input('price_per_night') ?? null,
                        'category' => $request->input('category') ?? null,
                        'services_offerts' => $request->input('services_offerts') ?? null,
                        'additional_services_description' => $request->input('additional_services_description') ?? null,
                        'legal_document' => null, // This is the corrected field name
                        'disponibilite' => true,
                        'ad_id' => null,
                        'photo_album_id' => null, 
                        // These fields can be added later if needed:
                        // 'latitude' => $request->input('latitude') ?? null,
                        // 'longitude' => $request->input('longitude') ?? null,
                        // 'established_date' => $request->input('established_date') ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Gérer les services pour les centres
                    $this->handleCenterServices($request, $centreId);
                    
                    // Gérer l'équipement pour les centres
                    $this->handleCenterEquipment($request, $centreId);
                    
                    // Gérer les images pour les centres (using albums table)
                    $this->handleCenterImages($request, $centreId, $user->id);
                    break;

                case 'groupe':
                    DB::table('profile_groupes')->insert([
                        'profile_id' => $profileId,
                        'nom_groupe' => $request->input('nom_groupe') ?? null,
                        'id_album_photo' => null,
                        'id_annonce' => null,
                        'cin_responsable' => $request->input('cin_responsable') ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;

                case 'fournisseur':
                    DB::table('profile_fournisseurs')->insert([
                        'profile_id' => $profileId,
                        'adresse' => $request->input('adresse') ?? null,
                        'cin' => $request->input('cin_fournisseur') ?? null,
                        'intervale_prix' => $request->input('interval_prix') ?? null,
                        'product_category' => $request->input('product_category') ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;

                case 'campeur':
                    // Pas de table fille
                    break;
            }

            DB::commit();

            $message = $isActive
                ? 'Inscription réussie ! Vous pouvez maintenant vous connecter.'
                : 'Inscription réussie ! Veuillez attendre l\'activation par un administrateur.';

            return response()->json([
                'message' => $message,
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Registration failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'error' => 'Une erreur est survenue lors de l\'inscription.',
                'details' => config('app.debug') ? $e->getMessage() : null,
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Gère les services du centre
     */
    private function handleCenterServices(Request $request, $centreId)
    {
        // Check if center_data exists and has services
        $centerData = $request->input('center_data');
        
        // If center_data is a string, decode it
        if (is_string($centerData)) {
            $centerData = json_decode($centerData, true);
        }
        
        if (is_array($centerData) && isset($centerData['services']) && is_array($centerData['services'])) {
            $services = $centerData['services'];
            
            foreach ($services as $service) {
                // Vérifier si la catégorie de service existe
                $serviceCategory = ServiceCategory::find($service['service_category_id'] ?? null);
                
                if ($serviceCategory) {
                    DB::table('profile_center_services')->insert([
                        'profile_center_id' => $centreId,
                        'service_category_id' => $serviceCategory->id,
                        'price' => $service['price'] ?? $serviceCategory->suggested_price,
                        'unit' => $service['unit'] ?? $serviceCategory->unit,
                        'description' => $service['description'] ?? $serviceCategory->description,
                        'is_available' => $service['is_available'] ?? true,
                        'is_standard' => $service['is_standard'] ?? $serviceCategory->is_standard,
                        'min_quantity' => $service['min_quantity'] ?? 1,
                        'max_quantity' => $service['max_quantity'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } else {
            // Si aucun service n'est fourni, ajouter le service standard par défaut
            $standardService = ServiceCategory::where('is_standard', true)->first();
            
            if ($standardService) {
                DB::table('profile_center_services')->insert([
                    'profile_center_id' => $centreId,
                    'service_category_id' => $standardService->id,
                    'price' => $standardService->suggested_price,
                    'unit' => $standardService->unit,
                    'description' => $standardService->description,
                    'is_available' => true,
                    'is_standard' => true,
                    'min_quantity' => 1,
                    'max_quantity' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Gère l'équipement du centre - UPDATED FOR YOUR SCHEMA
     */
    private function handleCenterEquipment(Request $request, $centreId)
    {
        // Types d'équipement disponibles
        $equipmentTypes = [
            'toilets' => 'Toilettes',
            'drinking_water' => 'Eau potable',
            'electricity' => 'Électricité',
            'parking' => 'Parking',
            'wifi' => 'WiFi',
            'showers' => 'Douches',
            'security' => 'Sécurité',
            'kitchen' => 'Cuisine',
            'bbq_area' => 'Zone BBQ',
            'swimming_pool' => 'Piscine',
        ];

        // Check if center_data exists and has equipment
        $centerData = $request->input('center_data');
        
        // If center_data is a string, decode it
        if (is_string($centerData)) {
            $centerData = json_decode($centerData, true);
        }
        
        if (is_array($centerData) && isset($centerData['equipment']) && is_array($centerData['equipment'])) {
            $equipmentData = $centerData['equipment'];
            
            foreach ($equipmentData as $eq) {
                if (isset($eq['type']) && array_key_exists($eq['type'], $equipmentTypes)) {
                    // Insert directly into profile_center_equipment table
                    DB::table('profile_center_equipment')->insert([
                        'profile_center_id' => $centreId,
                        'type' => $eq['type'],
                        'is_available' => $eq['is_available'] ?? false,
                        'notes' => $eq['notes'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } else {
            // Si aucune donnée d'équipement n'est fournie, ajouter l'équipement de base
            $basicEquipment = ['toilets', 'drinking_water', 'electricity', 'parking'];
            
            foreach ($basicEquipment as $type) {
                DB::table('profile_center_equipment')->insert([
                    'profile_center_id' => $centreId,
                    'type' => $type,
                    'is_available' => true,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Gère les images du centre - UPDATED FOR ALBUMS TABLE
     */
    private function handleCenterImages(Request $request, $centreId, $userId)
    {
        $centerData = $request->input('center_data');
        
        // If center_data is a string, decode it
        if (is_string($centerData)) {
            $centerData = json_decode($centerData, true);
        }
        
        if (is_array($centerData) && isset($centerData['images']) && is_array($centerData['images'])) {
            $images = $centerData['images'];
            
            if (count($images) > 0) {
                // Create an album for the center
                $albumId = DB::table('albums')->insertGetId([
                    'user_id' => $userId,
                    'name' => 'Center Images',
                    'description' => 'Images for camping center',
                    'type' => 'center',
                    'cover_image' => $images[0], // First image as cover
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Update the center with album ID
                DB::table('profile_centres')
                    ->where('id', $centreId)
                    ->update(['id_album_photo' => $albumId]);
                
                // Insert images into album_images table (assuming you have one)
                // Or store image URLs in a separate table
                foreach ($images as $index => $imageUrl) {
                    // Assuming you have an album_images table
                    if (DB::getSchemaBuilder()->hasTable('album_images')) {
                        DB::table('album_images')->insert([
                            'album_id' => $albumId,
                            'image_url' => $imageUrl,
                            'order' => $index,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}