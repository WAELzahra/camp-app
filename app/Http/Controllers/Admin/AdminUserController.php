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
                        'profileCentre.equipment',
                        'profileCentre.services',
                        'profileGroupe',
                        'profileFournisseur'
                    ]);
                },
                'albums.photos'
            ])->findOrFail($id);

            // Ajouter les documents formatés
            $userData = $user->toArray();
            $userData['documents'] = $this->getUserDocuments($user);

            return response()->json([
                'success' => true,
                'data' => $userData
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
            // Champs de base User
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone_number' => 'sometimes|string|max:20',
            'ville' => 'sometimes|string|nullable',
            'birthdate' => 'sometimes|date|nullable',
            'gender' => 'sometimes|string|in:male,female,other|nullable',
            'languages' => 'sometimes|string|nullable',
            'bio' => 'sometimes|string|nullable',
            'avatar' => 'sometimes|string|nullable',
            'cover_image' => 'sometimes|string|nullable',
            'role_id' => 'sometimes|exists:roles,id',
            'is_active' => 'sometimes|boolean',
            'first_login' => 'sometimes|boolean',
            'nombre_signalement' => 'sometimes|integer',
            
            // Adresse (sera dirigée vers le bon profil)
            'adresse' => 'sometimes|string|nullable',
            
            // Champs spécifiques aux guides
            'experience' => 'sometimes|string|nullable',
            'tarif' => 'sometimes|numeric|nullable',
            'zone_travail' => 'sometimes|string|nullable',
            'certificat_path' => 'sometimes|string|nullable',
            'certificat_filename' => 'sometimes|string|nullable',
            
            // Champs spécifiques aux centres
            'capacity' => 'sometimes|integer|nullable',
            'availability' => 'sometimes|string|nullable',
            'services_offerts' => 'sometimes|string|nullable',
            'price_per_night' => 'sometimes|numeric|nullable',
            'category' => 'sometimes|string|nullable',
            'document_legal' => 'sometimes|string|nullable',
            'document_legal_type' => 'sometimes|string|nullable',
            'document_legal_filename' => 'sometimes|string|nullable',
            'document_legal_expiration' => 'sometimes|date|nullable',
            
            // Champs spécifiques aux groupes
            'nom_groupe' => 'sometimes|string|nullable',
            'cin_responsable' => 'sometimes|string|nullable',
            'patente_path' => 'sometimes|string|nullable',
            'patente_filename' => 'sometimes|string|nullable',
            'cin_responsable_path' => 'sometimes|string|nullable',
            'cin_responsable_filename' => 'sometimes|string|nullable',
            
            // Champs spécifiques aux fournisseurs
            'intervale_prix' => 'sometimes|string|nullable',
            'product_category' => 'sometimes|string|nullable',
            'cin_commercant_path' => 'sometimes|string|nullable',
            'cin_commercant_filename' => 'sometimes|string|nullable',
            'registre_commerce_path' => 'sometimes|string|nullable',
            'registre_commerce_filename' => 'sometimes|string|nullable',
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
     * Mettre à jour les données du profil
     */
    private function updateProfileData($profile, $data)
{
    $profileData = [];
    if (isset($data['bio'])) $profileData['bio'] = $data['bio'];
    if (isset($data['cover_image'])) $profileData['cover_image'] = $data['cover_image'];
    if (isset($data['adresse']) && !$this->hasSpecificProfile($data)) {
        // Si l'utilisateur n'a pas de profil spécifique (campeur)
        $profileData['adresse'] = $data['adresse'];
    }
    
    if (!empty($profileData)) {
        $profile->update($profileData);
    }
}

private function hasSpecificProfile($data)
{
    return isset($data['capacity']) || 
           isset($data['experience']) || 
           isset($data['nom_groupe']) || 
           isset($data['intervale_prix']);
}

    /**
     * Mettre à jour le profil spécifique
     */
  private function updateSpecificProfile($user, $data)
{
    $roleName = $user->role ? strtolower($user->role->name) : null;

    switch ($roleName) {
        case 'guide':
            if ($user->profile && $user->profile->profileGuide) {
                $guideData = [];
                if (isset($data['adresse'])) $guideData['adresse'] = $data['adresse'];
                if (isset($data['experience'])) $guideData['experience'] = $data['experience'];
                if (isset($data['tarif'])) $guideData['tarif'] = $data['tarif'];
                if (isset($data['zone_travail'])) $guideData['zone_travail'] = $data['zone_travail'];
                if (isset($data['certificat_path'])) $guideData['certificat_path'] = $data['certificat_path'];
                if (isset($data['certificat_filename'])) $guideData['certificat_filename'] = $data['certificat_filename'];
                
                if (!empty($guideData)) {
                    $user->profile->profileGuide->update($guideData);
                }
            }
            break;

        case 'centre':
        case 'center':
            if ($user->profile && $user->profile->profileCentre) {
                $centreData = [];
                if (isset($data['adresse'])) $centreData['adresse'] = $data['adresse'];
                if (isset($data['capacity'])) $centreData['capacite'] = $data['capacity'];
                if (isset($data['availability'])) $centreData['disponibilite'] = $data['availability'] === '24/7';
                if (isset($data['services_offerts'])) $centreData['services_offerts'] = $data['services_offerts'];
                if (isset($data['price_per_night'])) $centreData['price_per_night'] = $data['price_per_night'];
                if (isset($data['category'])) $centreData['category'] = $data['category'];
                if (isset($data['document_legal'])) $centreData['document_legal'] = $data['document_legal'];
                if (isset($data['document_legal_type'])) $centreData['document_legal_type'] = $data['document_legal_type'];
                if (isset($data['document_legal_filename'])) $centreData['document_legal_filename'] = $data['document_legal_filename'];
                if (isset($data['document_legal_expiration'])) $centreData['document_legal_expiration'] = $data['document_legal_expiration'];
                
                if (!empty($centreData)) {
                    $user->profile->profileCentre->update($centreData);
                }
            }
            break;

        case 'groupe':
        case 'group':
            if ($user->profile && $user->profile->profileGroupe) {
                $groupeData = [];
                if (isset($data['adresse'])) $groupeData['adresse'] = $data['adresse'];
                if (isset($data['nom_groupe'])) $groupeData['nom_groupe'] = $data['nom_groupe'];
                if (isset($data['cin_responsable'])) $groupeData['cin_responsable'] = $data['cin_responsable'];
                if (isset($data['patente_path'])) $groupeData['patente_path'] = $data['patente_path'];
                if (isset($data['patente_filename'])) $groupeData['patente_filename'] = $data['patente_filename'];
                if (isset($data['cin_responsable_path'])) $groupeData['cin_responsable_path'] = $data['cin_responsable_path'];
                if (isset($data['cin_responsable_filename'])) $groupeData['cin_responsable_filename'] = $data['cin_responsable_filename'];
                
                if (!empty($groupeData)) {
                    $user->profile->profileGroupe->update($groupeData);
                }
            }
            break;

        case 'fournisseur':
        case 'supplier':
            if ($user->profile && $user->profile->profileFournisseur) {
                $fournisseurData = [];
                if (isset($data['adresse'])) $fournisseurData['adresse'] = $data['adresse'];
                if (isset($data['intervale_prix'])) $fournisseurData['intervale_prix'] = $data['intervale_prix'];
                if (isset($data['product_category'])) $fournisseurData['product_category'] = $data['product_category'];
                if (isset($data['cin_commercant_path'])) $fournisseurData['cin_commercant_path'] = $data['cin_commercant_path'];
                if (isset($data['cin_commercant_filename'])) $fournisseurData['cin_commercant_filename'] = $data['cin_commercant_filename'];
                if (isset($data['registre_commerce_path'])) $fournisseurData['registre_commerce_path'] = $data['registre_commerce_path'];
                if (isset($data['registre_commerce_filename'])) $fournisseurData['registre_commerce_filename'] = $data['registre_commerce_filename'];
                
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

            // Documents centre
            if ($user->profile->profileCentre) {
                $centre = $user->profile->profileCentre;
                $documents['centre'] = [
                    'document_legal' => $centre->document_legal,
                    'document_legal_type' => $centre->document_legal_type,
                    'document_legal_filename' => $centre->document_legal_filename,
                    'document_legal_expiration' => $centre->document_legal_expiration,
                    'document_legal_url' => $centre->document_legal ? asset('storage/' . $centre->document_legal) : null,
                ];
            }

            // Documents groupe
            if ($user->profile->profileGroupe) {
                $groupe = $user->profile->profileGroupe;
                $documents['groupe'] = [
                    'cin_responsable' => $groupe->cin_responsable,
                    'patente_path' => $groupe->patente_path,
                    'patente_filename' => $groupe->patente_filename,
                    'patente_url' => $groupe->patente_path ? asset('storage/' . $groupe->patente_path) : null,
                    'cin_responsable_path' => $groupe->cin_responsable_path,
                    'cin_responsable_filename' => $groupe->cin_responsable_filename,
                    'cin_responsable_url' => $groupe->cin_responsable_path ? asset('storage/' . $groupe->cin_responsable_path) : null,
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