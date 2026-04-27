<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use App\Models\ProfileGuide;
use App\Models\ProfileCentre;
use App\Models\ProfileGroupe;
use App\Models\ProfileFournisseur;
use App\Models\Feedbacks;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountStatusChanged;
use App\Mail\PasswordResetEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    /**
     * Affiche la liste des utilisateurs avec filtres
     */
    public function index(Request $request)
    {
        try {
            $role = $request->query('role');
            $status = $request->query('status');
            $dateFilter = $request->query('date');
            $search = $request->query('search');

            $query = User::with([
                'role',
                'profile.profileGuide',
                'profile.profileCentre',
                'profile.profileGroupe',
                'profile.profileFournisseur'
            ])->where('id', '!=', auth()->id());

            // Filtre par rôle
            if ($role && $role !== 'all') {
                $query->whereHas('role', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            }

            // Filtre par statut
            if ($status && $status !== 'all') {
                $query->where('is_active', $status === 'active');
            }

            // Filtre par date
            if ($dateFilter && $dateFilter !== 'all') {
                switch ($dateFilter) {
                    case 'today':
                        $query->whereDate('created_at', today());
                        break;
                    case 'week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'month':
                        $query->whereMonth('created_at', now()->month);
                        break;
                    case 'year':
                        $query->whereYear('created_at', now()->year);
                        break;
                }
            }

            // Recherche
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone_number', 'LIKE', "%{$search}%")
                      ->orWhereHas('role', function ($roleQuery) use ($search) {
                          $roleQuery->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }

            $users = $query->latest()->paginate(15);

            // Transformer les données pour le frontend
            $users->getCollection()->transform(function ($user) {
                return $this->formatUserForFrontend($user);
            });

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'Utilisateurs récupérés avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs'
            ], 500);
        }
    }

    /**
     * Affiche le profil complet d'un utilisateur
     */
    public function show($id)
    {
        try {
            $user = User::with([
                'role',
                'profile' => function($q) {
                    $q->with([
                        'profileGuide',
                        'profileCentre',
                        'profileGroupe',
                        'profileFournisseur',
                    ]);
                },
            ])->findOrFail($id);

            $userData = $this->formatUserForFrontend($user);

            // Inject role-specific nested profile data so the modal can populate its fields
            if ($user->profile) {
                $profileExtra = [];

                if ($user->profile->profileCentre) {
                    $pc = $user->profile->profileCentre;
                    $profileExtra['profile_centre'] = [
                        'name'                      => $pc->name,
                        'capacite'                  => $pc->capacite,
                        'price_per_night'           => $pc->price_per_night,
                        'category'                  => $pc->category,
                        'disponibilite'             => (bool) $pc->disponibilite,
                        'legal_document'            => $pc->legal_document,
                        'document_legal_type'       => $pc->document_legal_type,
                        'document_legal_expiration' => $pc->document_legal_expiration
                            ? $pc->document_legal_expiration->format('Y-m-d') : null,
                        'contact_email'             => $pc->contact_email,
                        'contact_phone'             => $pc->contact_phone,
                        'manager_name'              => $pc->manager_name,
                        'established_date'          => $pc->established_date
                            ? $pc->established_date->format('Y-m-d') : null,
                        'latitude'                  => $pc->latitude,
                        'longitude'                 => $pc->longitude,
                    ];
                }

                if ($user->profile->profileGuide) {
                    $pg = $user->profile->profileGuide;
                    $profileExtra['profile_guide'] = [
                        'experience'            => $pg->experience,
                        'tarif'                 => $pg->tarif,
                        'zone_travail'          => $pg->zone_travail,
                        'certificat_path'       => $pg->certificat_path,
                        'certificat_type'       => $pg->certificat_type,
                        'certificat_expiration' => $pg->certificat_expiration
                            ? $pg->certificat_expiration->format('Y-m-d') : null,
                    ];
                }

                if ($user->profile->profileGroupe) {
                    $pg = $user->profile->profileGroupe;
                    $profileExtra['profile_groupe'] = [
                        'nom_groupe'   => $pg->nom_groupe,
                        'patente_path' => $pg->patente_path,
                    ];
                }

                if ($user->profile->profileFournisseur) {
                    $pf = $user->profile->profileFournisseur;
                    $profileExtra['profile_fournisseur'] = [
                        'intervale_prix'   => $pf->intervale_prix,
                        'product_category' => $pf->product_category,
                    ];
                }

                $userData['profile'] = array_merge($userData['profile'] ?? [], $profileExtra);
            }

            return response()->json([
                'success' => true,
                'data'    => $userData,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du profil'
            ], 500);
        }
    }

          public function update(Request $request, $id)
{
    try {
        $user = User::with(['profile', 'role'])->findOrFail($id);

        // Validation
        $validated = $request->validate([
            // users table
            'first_name'         => 'sometimes|string|max:255',
            'last_name'          => 'sometimes|string|max:255',
            'email'              => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone_number'       => 'sometimes|string|max:20|nullable',
            'ville'              => 'sometimes|string|nullable',
            'birthdate'          => 'sometimes|date|nullable',
            'gender'             => 'sometimes|string|in:male,female,other|nullable',
            'languages'          => 'sometimes|string|nullable',
            'avatar'             => 'sometimes|string|nullable',
            'cover_image'        => 'sometimes|string|nullable',
            'role_id'            => 'sometimes|exists:roles,id',
            'is_active'          => 'sometimes|boolean',
            'first_login'        => 'sometimes|boolean',
            'nombre_signalement' => 'sometimes|integer',

            // profiles table
            'bio'        => 'sometimes|string|nullable',
            'city'       => 'sometimes|string|nullable',
            'address'    => 'sometimes|string|nullable',
            'cin_path'   => 'sometimes|string|nullable',
            'activities' => 'sometimes|string|nullable',
            'is_public'  => 'sometimes|boolean',

            // profile_guides
            'experience'            => 'sometimes|integer|nullable',
            'tarif'                 => 'sometimes|numeric|nullable',
            'zone_travail'          => 'sometimes|string|nullable',
            'certificat_path'       => 'sometimes|string|nullable',
            'certificat_type'       => 'sometimes|string|nullable',
            'certificat_expiration' => 'sometimes|date|nullable',

            // profile_centres
            'centre_name'              => 'sometimes|string|nullable',
            'capacity'                 => 'sometimes|integer|nullable',
            'price_per_night'          => 'sometimes|numeric|nullable',
            'category'                 => 'sometimes|string|nullable',
            'disponibilite'            => 'sometimes|boolean',
            'legal_document'           => 'sometimes|string|nullable',
            'document_legal_type'      => 'sometimes|string|nullable',
            'document_legal_expiration'=> 'sometimes|date|nullable',
            'contact_email'            => 'sometimes|email|nullable',
            'contact_phone'            => 'sometimes|string|nullable',
            'manager_name'             => 'sometimes|string|nullable',
            'established_date'         => 'sometimes|date|nullable',
            'latitude'                 => 'sometimes|numeric|nullable',
            'longitude'                => 'sometimes|numeric|nullable',

            // profile_groupes
            'nom_groupe'  => 'sometimes|string|nullable',
            'patente_path'=> 'sometimes|string|nullable',

            // profile_fournisseurs
            'intervale_prix'   => 'sometimes|string|nullable',
            'product_category' => 'sometimes|string|nullable',
        ]);

        // Mise à jour de l'utilisateur
        $this->updateUserData($user, $validated);

        // Mise à jour du profil
        if ($user->profile) {
            $this->updateProfileData($user->profile, $validated);
            $this->updateSpecificProfile($user, $validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $this->formatUserForFrontend($user)
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Erreur update: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour'
        ], 500);
    }
}

    /**
     * Mettre à jour les données utilisateur
     */
    private function updateUserData($user, $data)
{
    $userData = [];
    $fields = [
        'first_name', 'last_name', 'email', 'phone_number', 
        // 'adresse' a été retiré - il est dans les profils spécifiques
        'ville', 'birthdate' => 'date_naissance',
        'gender' => 'sexe', 'languages' => 'langue', 'avatar',
        'is_active', 'role_id', 'first_login', 'nombre_signalement'
    ];

    foreach ($fields as $key => $field) {
        $inputKey = is_numeric($key) ? $field : $key;
        $dbField = is_numeric($key) ? $field : $field;
        
        if (isset($data[$inputKey])) {
            $userData[$dbField] = $data[$inputKey];
        }
    }

    if (!empty($userData)) {
        $user->update($userData);
    }
}

    /**
     * Mettre à jour les données du profil (table profiles)
     */
    private function updateProfileData($profile, $data)
{
    $map = [
        'bio'        => 'bio',
        'cover_image'=> 'cover_image',
        'city'       => 'city',
        'address'    => 'address',
        'cin_path'   => 'cin_path',
        'activities' => 'activities',
        'is_public'  => 'is_public',
    ];

    $profileData = [];
    foreach ($map as $input => $column) {
        if (array_key_exists($input, $data)) {
            $profileData[$column] = $data[$input];
        }
    }

    if (!empty($profileData)) {
        $profile->update($profileData);
    }
}

    /**
     * Mettre à jour le profil spécifique
     */
  private function updateSpecificProfile($user, $data)
{
    $roleName = $user->role ? strtolower($user->role->name) : null;

    switch ($roleName) {
        // ── profile_guides ────────────────────────────────────────────────────
        case 'guide':
            if ($user->profile && $user->profile->profileGuide) {
                $guideMap = [
                    'experience'            => 'experience',
                    'tarif'                 => 'tarif',
                    'zone_travail'          => 'zone_travail',
                    'certificat_path'       => 'certificat_path',
                    'certificat_type'       => 'certificat_type',
                    'certificat_expiration' => 'certificat_expiration',
                ];
                $guideData = [];
                foreach ($guideMap as $input => $col) {
                    if (array_key_exists($input, $data)) $guideData[$col] = $data[$input];
                }
                if (!empty($guideData)) {
                    $user->profile->profileGuide->update($guideData);
                }
            }
            break;

        // ── profile_centres ───────────────────────────────────────────────────
        case 'centre':
        case 'center':
            if ($user->profile) {
                // Create the profile_centre row if it doesn't exist yet
                if (!$user->profile->profileCentre) {
                    $user->profile->profileCentre()->create([]);
                    $user->profile->load('profileCentre');
                }
                $centreData = [];
                // name arrives as 'centre_name' to avoid collision with user first_name
                if (array_key_exists('centre_name', $data))               $centreData['name']                     = $data['centre_name'];
                if (array_key_exists('capacity', $data))                  $centreData['capacite']                 = $data['capacity'];
                if (array_key_exists('price_per_night', $data))           $centreData['price_per_night']          = $data['price_per_night'];
                if (array_key_exists('category', $data))                  $centreData['category']                 = $data['category'];
                if (array_key_exists('disponibilite', $data))             $centreData['disponibilite']            = (bool) $data['disponibilite'];
                if (array_key_exists('legal_document', $data))            $centreData['legal_document']           = $data['legal_document'];
                if (array_key_exists('document_legal_type', $data))       $centreData['document_legal_type']      = $data['document_legal_type'];
                if (array_key_exists('document_legal_expiration', $data)) $centreData['document_legal_expiration']= $data['document_legal_expiration'];
                if (array_key_exists('contact_email', $data))             $centreData['contact_email']            = $data['contact_email'];
                if (array_key_exists('contact_phone', $data))             $centreData['contact_phone']            = $data['contact_phone'];
                if (array_key_exists('manager_name', $data))              $centreData['manager_name']             = $data['manager_name'];
                if (array_key_exists('established_date', $data))          $centreData['established_date']         = $data['established_date'];
                if (array_key_exists('latitude', $data))                  $centreData['latitude']                 = $data['latitude'];
                if (array_key_exists('longitude', $data))                 $centreData['longitude']                = $data['longitude'];
                if (!empty($centreData)) {
                    $user->profile->profileCentre->update($centreData);
                }
            }
            break;

        // ── profile_groupes ───────────────────────────────────────────────────
        case 'groupe':
        case 'group':
            if ($user->profile) {
                if (!$user->profile->profileGroupe) {
                    $user->profile->profileGroupe()->create([]);
                    $user->profile->load('profileGroupe');
                }
                $groupeData = [];
                if (array_key_exists('nom_groupe',   $data)) $groupeData['nom_groupe']   = $data['nom_groupe'];
                if (array_key_exists('patente_path', $data)) $groupeData['patente_path'] = $data['patente_path'];
                if (!empty($groupeData)) {
                    $user->profile->profileGroupe->update($groupeData);
                }
            }
            break;

        // ── profile_fournisseurs ──────────────────────────────────────────────
        case 'fournisseur':
        case 'supplier':
            if ($user->profile) {
                if (!$user->profile->profileFournisseur) {
                    $user->profile->profileFournisseur()->create([]);
                    $user->profile->load('profileFournisseur');
                }
                $fournisseurData = [];
                if (array_key_exists('intervale_prix',   $data)) $fournisseurData['intervale_prix']   = $data['intervale_prix'];
                if (array_key_exists('product_category', $data)) $fournisseurData['product_category'] = $data['product_category'];
                if (!empty($fournisseurData)) {
                    $user->profile->profileFournisseur->update($fournisseurData);
                }
            }
            break;
            
        default:
            // Camper ou autres rôles sans profil spécifique
            // L'adresse n'est pas stockée pour ces rôles
            break;
    }
}

    /**
     * Upload de document
     */
    public function uploadDocument(Request $request, $id)
    {
        try {
            $user = User::with(['profile'])->findOrFail($id);
            
            $request->validate([
                'document_type' => 'required|string|in:cin,certificat,legal,patente,cin_responsable,cin_commercant,registre',
                'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            ]);

            $file = $request->file('document');
            $documentType = $request->document_type;
            
            // Générer un nom unique
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('documents/' . $documentType . '/' . $user->id, $filename, 'public');
            
            // Mettre à jour le champ correspondant
            $this->updateDocumentField($user, $documentType, $path, $file->getClientOriginalName());

            return response()->json([
                'success' => true,
                'message' => 'Document uploadé avec succès',
                'data' => [
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'filename' => $file->getClientOriginalName(),
                    'type' => $documentType
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur upload: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du document'
            ], 500);
        }
    }

    /**
     * Télécharger un document
     */
    public function downloadDocument($id, $documentType)
    {
        try {
            $user = User::findOrFail($id);
            $documentPath = $this->getDocumentPath($user, $documentType);
            
            if (!$documentPath || !Storage::disk('public')->exists($documentPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document non trouvé'
                ], 404);
            }

            return Storage::disk('public')->download($documentPath);

        } catch (\Exception $e) {
            Log::error('Erreur download: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement'
            ], 500);
        }
    }

    /**
     * Voir un document (aperçu)
     */
    public function viewDocument($id, $documentType)
    {
        try {
            $user = User::findOrFail($id);
            $documentPath = $this->getDocumentPath($user, $documentType);
            
            if (!$documentPath || !Storage::disk('public')->exists($documentPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document non trouvé'
                ], 404);
            }

            return response()->file(storage_path('app/public/' . $documentPath));

        } catch (\Exception $e) {
            Log::error('Erreur view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'affichage du document'
            ], 500);
        }
    }

    /**
     * Supprimer un document
     */
    public function deleteDocument($id, $documentType)
    {
        try {
            $user = User::findOrFail($id);
            
            $documentPath = $this->getDocumentPath($user, $documentType);
            
            if ($documentPath && Storage::disk('public')->exists($documentPath)) {
                Storage::disk('public')->delete($documentPath);
            }
            
            // Mettre à jour la base de données
            $this->deleteDocumentField($user, $documentType);

            return response()->json([
                'success' => true,
                'message' => 'Document supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur delete document: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword($id)
    {
        try {
            $user = User::findOrFail($id);

            $newPassword = Str::random(10);
            
            $user->password = Hash::make($newPassword);
            $user->save();

            try {
                Mail::to($user->email)->send(new PasswordResetEmail($user, $newPassword));
            } catch (\Exception $e) {
                Log::error('Erreur envoi email: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès',
                'data' => [
                    'email' => $user->email,
                    'temporary_password' => $newPassword
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur reset password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation'
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un utilisateur
     */
    public function toggleActivation($id)
    {
        try {
            $user = User::findOrFail($id);

            if (auth()->id() == $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas modifier votre propre compte'
                ], 403);
            }

            $user->is_active = $user->is_active == 1 ? 0 : 1;
            $user->save();

            // Envoyer email de notification
            try {
                Mail::to($user->email)->send(new AccountStatusChanged($user, $user->is_active));
            } catch (\Exception $e) {
                Log::error('Erreur envoi email: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'is_active' => $user->is_active
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur toggle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification'
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            if (auth()->id() == $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte'
                ], 403);
            }

            // Supprimer les fichiers physiques
            $this->deleteAllUserDocuments($user);

            // Supprimer les relations
            if ($user->profile) {
                $user->profile->profileGuide()?->delete();
                $user->profile->profileCentre()?->delete();
                $user->profile->profileGroupe()?->delete();
                $user->profile->profileFournisseur()?->delete();
                $user->profile->delete();
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Modérer un feedback
     */
    public function moderate(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:approved,rejected',
                'response' => 'nullable|string|max:1000',
            ]);

            $feedback = Feedbacks::findOrFail($id);
            $feedback->status = $request->status;
            $feedback->response = $request->response ?? null;
            $feedback->save();

            return response()->json([
                'success' => true,
                'message' => 'Feedback modéré avec succès',
                'data' => $feedback
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur moderate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modération'
            ], 500);
        }
    }

    /**
     * Statistiques
     */
    public function stats()
    {
        try {
            $stats = [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'by_role' => User::with('role')
                    ->get()
                    ->groupBy('role.name')
                    ->map(function ($users) {
                        return $users->count();
                    }),
                'recent' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    // ==================== MÉTHODES PRIVÉES ====================

    /**
     * Formater l'utilisateur pour le frontend
     */
    private function formatUserForFrontend($user)
    {
        $profileData = null;
        if ($user->profile) {
            $profileData = [
                'id' => $user->profile->id,
                'bio' => $user->profile->bio,
                'cover_image' => $user->profile->cover_image,
                'type' => $user->profile->type,
                'activities' => $user->profile->activities,
                'cin_path' => $user->profile->cin_path,
                'cin_filename' => $user->profile->cin_filename,
                'cin_url' => $user->profile->cin_path ? asset('storage/' . $user->profile->cin_path) : null,
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role ? $user->role->name : 'Camper',
            'role_id' => $user->role_id,
            'phone_number' => $user->phone_number,
            'avatar' => $user->avatar,
            'ville' => $user->ville,
            'adresse' => $user->adresse,
            'date_naissance' => $user->date_naissance,
            'sexe' => $user->sexe,
            'langue' => $user->langue,
            'first_login' => $user->first_login,
            'nombre_signalement' => $user->nombre_signalement,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'is_active' => $user->is_active ? 1 : 0,
            'status' => $user->is_active ? 'active' : 'inactive',
            'joinedDate' => $user->created_at ? $user->created_at->format('d M Y') : null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'profile' => $profileData,
            'documents' => $this->getUserDocuments($user),
        ];
    }

    /**
     * Récupérer tous les documents d'un utilisateur
     */
    private function getUserDocuments($user)
    {
        $documents = [
            'cin' => null,
            'guide' => null,
            'centre' => null,
            'groupe' => null,
            'fournisseur' => null,
        ];

        if ($user->profile) {
            // CIN (dans profile)
            $documents['cin'] = [
                'path' => $user->profile->cin_path,
                'filename' => $user->profile->cin_filename,
                'url' => $user->profile->cin_path ? asset('storage/' . $user->profile->cin_path) : null,
            ];

            // Documents guide
            if ($user->profile->profileGuide) {
                $guide = $user->profile->profileGuide;
                $documents['guide'] = [
                    'certificat_path' => $guide->certificat_path,
                    'certificat_filename' => $guide->certificat_filename,
                    'certificat_type' => $guide->certificat_type,
                    'certificat_expiration' => $guide->certificat_expiration,
                    'certificat_url' => $guide->certificat_path ? asset('storage/' . $guide->certificat_path) : null,
                ];
            }

            // Documents centre — column on profile_centres is `legal_document`
            if ($user->profile->profileCentre) {
                $centre = $user->profile->profileCentre;
                $documents['centre'] = [
                    'document_legal'            => $centre->legal_document,
                    'document_legal_type'       => $centre->document_legal_type,
                    'document_legal_expiration' => $centre->document_legal_expiration,
                    'document_legal_url'        => $centre->legal_document
                                                    ? asset('storage/' . $centre->legal_document)
                                                    : null,
                ];
            }

            // Documents groupe — only patente_path exists in profile_groupes table
            if ($user->profile->profileGroupe) {
                $groupe = $user->profile->profileGroupe;
                $documents['groupe'] = [
                    'patente_path' => $groupe->patente_path,
                    'patente_url'  => $groupe->patente_path
                                        ? asset('storage/' . $groupe->patente_path)
                                        : null,
                ];
            }

            // Documents fournisseur
            if ($user->profile->profileFournisseur) {
                $fournisseur = $user->profile->profileFournisseur;
                $documents['fournisseur'] = [
                    'cin_commercant_path' => $fournisseur->cin_commercant_path,
                    'cin_commercant_filename' => $fournisseur->cin_commercant_filename,
                    'cin_commercant_url' => $fournisseur->cin_commercant_path ? asset('storage/' . $fournisseur->cin_commercant_path) : null,
                    'registre_commerce_path' => $fournisseur->registre_commerce_path,
                    'registre_commerce_filename' => $fournisseur->registre_commerce_filename,
                    'registre_commerce_url' => $fournisseur->registre_commerce_path ? asset('storage/' . $fournisseur->registre_commerce_path) : null,
                ];
            }
        }

        return $documents;
    }

    


    /**
     * Mettre à jour le champ de document
     */
    private function updateDocumentField($user, $documentType, $path, $filename)
    {
        if (!$user->profile) {
            return;
        }

        switch ($documentType) {
            case 'cin':
                $user->profile->cin_path = $path;
                $user->profile->cin_filename = $filename;
                $user->profile->save();
                break;

            case 'certificat':
                if ($user->profile->profileGuide) {
                    $user->profile->profileGuide->certificat_path = $path;
                    $user->profile->profileGuide->certificat_filename = $filename;
                    $user->profile->profileGuide->save();
                }
                break;

            case 'legal':
                if ($user->profile->profileCentre) {
                    $user->profile->profileCentre->document_legal = $path;
                    $user->profile->profileCentre->document_legal_filename = $filename;
                    $user->profile->profileCentre->save();
                }
                break;

            case 'patente':
                if ($user->profile->profileGroupe) {
                    $user->profile->profileGroupe->patente_path = $path;
                    $user->profile->profileGroupe->patente_filename = $filename;
                    $user->profile->profileGroupe->save();
                }
                break;

            case 'cin_responsable':
                if ($user->profile->profileGroupe) {
                    $user->profile->profileGroupe->cin_responsable_path = $path;
                    $user->profile->profileGroupe->cin_responsable_filename = $filename;
                    $user->profile->profileGroupe->save();
                }
                break;

            case 'cin_commercant':
                if ($user->profile->profileFournisseur) {
                    $user->profile->profileFournisseur->cin_commercant_path = $path;
                    $user->profile->profileFournisseur->cin_commercant_filename = $filename;
                    $user->profile->profileFournisseur->save();
                }
                break;

            case 'registre':
                if ($user->profile->profileFournisseur) {
                    $user->profile->profileFournisseur->registre_commerce_path = $path;
                    $user->profile->profileFournisseur->registre_commerce_filename = $filename;
                    $user->profile->profileFournisseur->save();
                }
                break;
        }
    }

    /**
     * Récupérer le chemin d'un document
     */
    private function getDocumentPath($user, $documentType)
    {
        if (!$user->profile) {
            return null;
        }

        switch ($documentType) {
            case 'cin':
                return $user->profile->cin_path;
            case 'certificat':
                return $user->profile->profileGuide?->certificat_path;
            case 'legal':
                return $user->profile->profileCentre?->document_legal;
            case 'patente':
                return $user->profile->profileGroupe?->patente_path;
            case 'cin_responsable':
                return $user->profile->profileGroupe?->cin_responsable_path;
            case 'cin_commercant':
                return $user->profile->profileFournisseur?->cin_commercant_path;
            case 'registre':
                return $user->profile->profileFournisseur?->registre_commerce_path;
            default:
                return null;
        }
    }

    /**
     * Supprimer le champ de document
     */
    private function deleteDocumentField($user, $documentType)
    {
        if (!$user->profile) {
            return;
        }

        switch ($documentType) {
            case 'cin':
                $user->profile->cin_path = null;
                $user->profile->cin_filename = null;
                $user->profile->save();
                break;

            case 'certificat':
                if ($user->profile->profileGuide) {
                    $user->profile->profileGuide->certificat_path = null;
                    $user->profile->profileGuide->certificat_filename = null;
                    $user->profile->profileGuide->save();
                }
                break;

            case 'legal':
                if ($user->profile->profileCentre) {
                    $user->profile->profileCentre->document_legal = null;
                    $user->profile->profileCentre->document_legal_filename = null;
                    $user->profile->profileCentre->save();
                }
                break;

            case 'patente':
                if ($user->profile->profileGroupe) {
                    $user->profile->profileGroupe->patente_path = null;
                    $user->profile->profileGroupe->patente_filename = null;
                    $user->profile->profileGroupe->save();
                }
                break;

            case 'cin_responsable':
                if ($user->profile->profileGroupe) {
                    $user->profile->profileGroupe->cin_responsable_path = null;
                    $user->profile->profileGroupe->cin_responsable_filename = null;
                    $user->profile->profileGroupe->save();
                }
                break;

            case 'cin_commercant':
                if ($user->profile->profileFournisseur) {
                    $user->profile->profileFournisseur->cin_commercant_path = null;
                    $user->profile->profileFournisseur->cin_commercant_filename = null;
                    $user->profile->profileFournisseur->save();
                }
                break;

            case 'registre':
                if ($user->profile->profileFournisseur) {
                    $user->profile->profileFournisseur->registre_commerce_path = null;
                    $user->profile->profileFournisseur->registre_commerce_filename = null;
                    $user->profile->profileFournisseur->save();
                }
                break;
        }
    }

    /**
     * Supprimer tous les documents d'un utilisateur
     */
    private function deleteAllUserDocuments($user)
    {
        $documentPaths = [
            $user->profile?->cin_path,
            $user->profile?->profileGuide?->certificat_path,
            $user->profile?->profileCentre?->document_legal,
            $user->profile?->profileGroupe?->patente_path,
            $user->profile?->profileGroupe?->cin_responsable_path,
            $user->profile?->profileFournisseur?->cin_commercant_path,
            $user->profile?->profileFournisseur?->registre_commerce_path,
        ];

        foreach ($documentPaths as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    
    /**
     * Uploader des photos pour l'album d'un utilisateur
     */
public function uploadPhotos(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Validation
            $request->validate([
                'photos' => 'required|array',
                'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);

            // Créer ou récupérer l'album de l'utilisateur
            $album = $user->albums()->firstOrCreate(
                ['user_id' => $user->id],
                ['titre' => 'Album principal']
            );

            $uploadedPhotos = [];
            
            foreach ($request->file('photos') as $photo) {
                // Générer un nom unique
                $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $path = $photo->storeAs('albums/' . $user->id, $filename, 'public');

                // Créer la photo
                $photoModel = $album->photos()->create([
                    'path_to_img' => $path,
                    'user_id' => $user->id,
                    'order' => $album->photos()->count() + 1
                ]);

                $uploadedPhotos[] = [
                    'id' => $photoModel->id,
                    'url' => asset('storage/' . $path),
                    'path' => $path,
                    'name' => $photo->getClientOriginalName()
                ];
            }

            return response()->json([
                'success' => true,
                'message' => count($uploadedPhotos) . ' photo(s) uploadée(s)',
                'data' => $uploadedPhotos
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur upload: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer toutes les photos d'un utilisateur
     */
    public function getPhotos($id)
    {
        try {
            $user = User::with('albums.photos')->findOrFail($id);
            
            $photos = [];
            foreach ($user->albums as $album) {
                foreach ($album->photos as $photo) {
                    $photos[] = [
                        'id' => $photo->id,
                        'url' => asset('storage/' . $photo->path_to_img),
                        'path' => $photo->path_to_img,
                        'album_id' => $album->id,
                        'order' => $photo->order,
                        'created_at' => $photo->created_at
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $photos
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur get photos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des photos'
            ], 500);
        }
    }
    /**
     * Supprimer une photo
     */
    public function deletePhoto($id, $photoId)
    {
        try {
            $user = User::findOrFail($id);
            
            $photo = \App\Models\Photo::where('id', $photoId)
                ->whereHas('album', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->firstOrFail();

            // Supprimer le fichier physique
            if (Storage::disk('public')->exists($photo->path_to_img)) {
                Storage::disk('public')->delete($photo->path_to_img);
            }

            // Supprimer l'entrée en base
            $photo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Photo supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur delete photo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la photo'
            ], 500);
        }
    }

    // Modifiez votre méthode update pour inclure la gestion des photos


    /**
     * Synchroniser les photos de l'album
     */
    private function syncAlbumPhotos($user, array $photoUrls)
    {
        try {
            // Récupérer ou créer l'album principal
            $album = $user->albums()->firstOrCreate([
                'user_id' => $user->id,
                'titre' => 'Album principal'
            ]);

            // Récupérer les photos existantes
            $existingPhotos = $album->photos()->pluck('path_to_img')->toArray();
            
            // URLs qui sont des chemins de stockage (pas des URLs complètes)
            $validPaths = array_filter($photoUrls, function($url) {
                return !filter_var($url, FILTER_VALIDATE_URL) || strpos($url, '/storage/') !== false;
            });

            // Extraire les chemins relatifs
            $newPaths = array_map(function($url) {
                if (strpos($url, '/storage/') !== false) {
                    return str_replace('/storage/', '', $url);
                }
                return $url;
            }, $validPaths);

            // Photos à supprimer (existantes mais plus dans la liste)
            $toDelete = array_diff($existingPhotos, $newPaths);
            
            foreach ($toDelete as $path) {
                $photo = $album->photos()->where('path_to_img', $path)->first();
                if ($photo) {
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                    $photo->delete();
                }
            }

            // Photos à ajouter (nouvelles)
            $toAdd = array_diff($newPaths, $existingPhotos);
            
            foreach ($toAdd as $index => $path) {
                // Vérifier si c'est une URL ou un chemin
                if (filter_var($path, FILTER_VALIDATE_URL) && strpos($path, '/storage/') === false) {
                    // C'est une URL externe, on ne peut pas la stocker
                    continue;
                }

                $album->photos()->create([
                    'path_to_img' => $path,
                    'user_id' => $user->id,
                    'order' => $album->photos()->count() + 1
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Erreur sync album photos: ' . $e->getMessage());
        }
    }

}