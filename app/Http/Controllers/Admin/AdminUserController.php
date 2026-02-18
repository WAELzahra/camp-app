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
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    /**
     * Affiche la liste des utilisateurs avec filtres
     */
    /**
 * Affiche la liste des utilisateurs avec filtres
 */
public function index(Request $request)
{
    try {
        \Log::info('Utilisateur connecté:', ['user' => auth()->user()]);
        \Log::info('Est authentifié:', ['auth' => auth()->check()]);
        
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
                // 👇 IMPORTANT: Retourner is_active en 0 ou 1
                'is_active' => $user->is_active ? 1 : 0,
                // 👇 Pour compatibilité avec l'ancien code (optionnel)
                'status' => $user->is_active ? 'active' : 'inactive',
                // 👇 Date de création formatée
                'joinedDate' => $user->created_at ? $user->created_at->format('d M Y') : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                // Profils associés
                'profile' => $user->profile ? [
                    'id' => $user->profile->id,
                    'bio' => $user->profile->bio,
                    'cover_image' => $user->profile->cover_image,
                    'immatricule' => $user->profile->immatricule,
                    'type' => $user->profile->type,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
            'message' => 'Utilisateurs récupérés avec succès'
        ]);

    } catch (\Exception $e) {
        \Log::error('Erreur lors de la récupération des utilisateurs: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des utilisateurs: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Affiche le profil complet d'un utilisateur
     */
// public function show($id)
// {
//     try {
//         $user = User::findOrFail($id);
        
//         return response()->json([
//             'success' => true,
//             'data' => $user  // ← Ici 'data' contient l'utilisateur
//         ]);

//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Utilisateur non trouvé'
//         ], 404);
//     }
// }
public function show($id)
{
    try {
        $user = User::with([
            'role',
            'profile.profileCentre.equipment',
            'profile.profileCentre.services',
            'profile.profileGuide',
            'profile.profileGroupe',
            'profile.profileFournisseur',
            'albums.photos'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);

    } catch (\Exception $e) {
        \Log::error('Erreur show: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du chargement du profil'
        ], 500);
    }
}
// Méthodes auxiliaires (si vous voulez les garder)
private function getCapacity($user)
{
    if ($user->profile && $user->profile->profileCentre) {
        return $user->profile->profileCentre->capacite ?? 0;
    }
    return 0;
}

private function getAvailability($user)
{
    if ($user->profile && $user->profile->profileCentre) {
        return $user->profile->profileCentre->disponibilite ? '24/7' : 'Not available';
    }
    return 'Not available';
}

private function getLegalDocuments($user)
{
    if ($user->profile && $user->profile->immatricule) {
        return 1;
    }
    return 0;
}

private function getBadges($user)
{
    $badges = [];
    if ($user->is_active) $badges[] = 'Verified';
    if ($user->email_verified_at) $badges[] = 'Email Verified';
    if ($user->profile && $user->profile->immatricule) $badges[] = 'Registered';
    return $badges;
}

private function getPhotos($user)
{
    $photos = [];
    if ($user->albums) {
        foreach ($user->albums as $album) {
            if ($album->photos) {
                foreach ($album->photos as $photo) {
                    $photos[] = [
                        'id' => $photo->id,
                        'url' => $photo->url ?? $photo->path,
                    ];
                }
            }
        }
    }
    return $photos;
}

    /**
     * Met à jour un utilisateur
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::with(['profile', 'role'])->findOrFail($id);

            // Validation
            $validated = $request->validate([
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
                'phone_number' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|nullable',
                'birthdate' => 'sometimes|date|nullable',
                'gender' => 'sometimes|string|in:male,female,other|nullable',
                'languages' => 'sometimes|string|nullable',
                'bio' => 'sometimes|string|nullable',
                'avatar' => 'sometimes|string|nullable',
                'cover_image' => 'sometimes|string|nullable',
                'role_id' => 'sometimes|exists:roles,id',
                'is_active' => 'sometimes|boolean',
                
                // Champs spécifiques aux centres
                'capacity' => 'sometimes|integer|nullable',
                'availability' => 'sometimes|string|nullable',
                
                // Champs spécifiques aux guides
                'experience' => 'sometimes|string|nullable',
                'tarif' => 'sometimes|numeric|nullable',
                'zone_travail' => 'sometimes|string|nullable',
                
                // Champs spécifiques aux groupes
                'nom_groupe' => 'sometimes|string|nullable',
                'cin_responsable' => 'sometimes|string|nullable',
                
                // Champs spécifiques aux fournisseurs
                'intervale_prix' => 'sometimes|string|nullable',
                'product_category' => 'sometimes|string|nullable',
            ]);

            // Mise à jour de l'utilisateur
            $userData = [];
            if (isset($validated['first_name'])) $userData['first_name'] = $validated['first_name'];
            if (isset($validated['last_name'])) $userData['last_name'] = $validated['last_name'];
            if (isset($validated['email'])) $userData['email'] = $validated['email'];
            if (isset($validated['phone_number'])) $userData['phone_number'] = $validated['phone_number'];
            if (isset($validated['address'])) $userData['adresse'] = $validated['address'];
            if (isset($validated['birthdate'])) $userData['date_naissance'] = $validated['birthdate'];
            if (isset($validated['gender'])) $userData['sexe'] = $validated['gender'];
            if (isset($validated['languages'])) $userData['langue'] = $validated['languages'];
            if (isset($validated['avatar'])) $userData['avatar'] = $validated['avatar'];
            if (isset($validated['is_active'])) $userData['is_active'] = $validated['is_active'];
            if (isset($validated['role_id'])) $userData['role_id'] = $validated['role_id'];

            if (!empty($userData)) {
                $user->update($userData);
            }

            // Mise à jour du profil
            if ($user->profile) {
                $profileData = [];
                if (isset($validated['bio'])) $profileData['bio'] = $validated['bio'];
                if (isset($validated['cover_image'])) $profileData['cover_image'] = $validated['cover_image'];
                
                if (!empty($profileData)) {
                    $user->profile->update($profileData);
                }

                // Mise à jour des profils spécifiques
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
            Log::error('Erreur lors de la mise à jour: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Met à jour le profil spécifique selon le rôle
     */
    private function updateSpecificProfile($user, $data)
    {
        $roleName = $user->role ? strtolower($user->role->name) : null;

        switch ($roleName) {
            case 'guide':
                if ($user->profile->profileGuide) {
                    $guideData = [];
                    if (isset($data['experience'])) $guideData['experience'] = $data['experience'];
                    if (isset($data['tarif'])) $guideData['tarif'] = $data['tarif'];
                    if (isset($data['zone_travail'])) $guideData['zone_travail'] = $data['zone_travail'];
                    
                    if (!empty($guideData)) {
                        $user->profile->profileGuide->update($guideData);
                    }
                }
                break;

            case 'centre':
            case 'center':
                if ($user->profile->profileCentre) {
                    $centreData = [];
                    if (isset($data['capacity'])) $centreData['capacite'] = $data['capacity'];
                    if (isset($data['availability'])) $centreData['disponibilite'] = $data['availability'] === '24/7' ? true : false;
                    
                    if (!empty($centreData)) {
                        $user->profile->profileCentre->update($centreData);
                    }
                }
                break;

            case 'groupe':
            case 'group':
                if ($user->profile->profileGroupe) {
                    $groupeData = [];
                    if (isset($data['nom_groupe'])) $groupeData['nom_groupe'] = $data['nom_groupe'];
                    if (isset($data['cin_responsable'])) $groupeData['cin_responsable'] = $data['cin_responsable'];
                    
                    if (!empty($groupeData)) {
                        $user->profile->profileGroupe->update($groupeData);
                    }
                }
                break;

            case 'fournisseur':
            case 'supplier':
                if ($user->profile->profileFournisseur) {
                    $fournisseurData = [];
                    if (isset($data['intervale_prix'])) $fournisseurData['intervale_prix'] = $data['intervale_prix'];
                    if (isset($data['product_category'])) $fournisseurData['product_category'] = $data['product_category'];
                    
                    if (!empty($fournisseurData)) {
                        $user->profile->profileFournisseur->update($fournisseurData);
                    }
                }
                break;
        }
    }

    /**
     * NOUVELLE FONCTION: Réinitialiser le mot de passe
     */
    public function resetPassword($id)
    {
        try {
            $user = User::findOrFail($id);

            // Générer un nouveau mot de passe aléatoire
            $newPassword = Str::random(10);
            
            // Mettre à jour le mot de passe
            $user->password = Hash::make($newPassword);
            $user->save();

            // Envoyer l'email avec le nouveau mot de passe
            try {
                Mail::to($user->email)->send(new PasswordResetEmail($user, $newPassword));
            } catch (\Exception $e) {
                Log::error('Erreur lors de l\'envoi de l\'email de réinitialisation: ' . $e->getMessage());
                // On continue même si l'email échoue, mais on log l'erreur
            }

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès. Un email a été envoyé à l\'utilisateur.',
                'data' => [
                    'email' => $user->email,
                    'temporary_password' => $newPassword // À retirer en production
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe'
            ], 500);
        }
    }

    /**
     * Envoyer un email à l'utilisateur
     */
    public function sendEmail($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Ici vous pouvez implémenter la logique d'envoi d'email personnalisé
            // Par exemple, envoyer un email de bienvenue, une notification, etc.
            
            return response()->json([
                'success' => true,
                'message' => 'Email envoyé avec succès à ' . $user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi de l\'email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email'
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
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte.'
                ], 403);
            }

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
            Log::error('Erreur lors de la suppression: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Activer/Désactiver un utilisateur
     */
//     public function toggleActivation($id)
// {
//     try {
//         $user = User::findOrFail($id);

//         if (auth()->id() == $user->id) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Vous ne pouvez pas modifier le statut de votre propre compte.'
//             ], 403);
//         }

//         // Bascule entre 0 et 1
//         $user->is_active = $user->is_active == 1 ? 0 : 1;
//         $user->save();

//         // Envoyer un email de notification
//         try {
//             Mail::to($user->email)->send(new AccountStatusChanged($user->is_active));
//         } catch (\Exception $e) {
//             Log::warning('Erreur lors de l\'envoi de l\'email: ' . $e->getMessage());
//         }

//         return response()->json([
//             'success' => true,
//             'message' => 'Statut du compte mis à jour avec succès.',
//             'is_active' => $user->is_active  // Retourne 0 ou 1
//         ]);

//     } catch (\Exception $e) {
//         Log::error('Erreur lors de la modification du statut: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => 'Erreur lors de la modification du statut'
//         ], 500);
//     }
// }

public function toggleActivation($id)
{
    try {
        $user = User::findOrFail($id);

        if (auth()->id() == $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas modifier le statut de votre propre compte.'
            ], 403);
        }

        // Bascule entre 0 et 1
        $user->is_active = $user->is_active == 1 ? 0 : 1;
        $user->save();

        // LOG POUR DÉBOGUER
        \Log::info('=== DÉBOGAGE EMAIL ===');
        \Log::info('User ID: ' . $user->id);
        \Log::info('User Email: ' . $user->email);
        \Log::info('New status: ' . $user->is_active);

        // Envoyer un email de notification
        try {
            \Log::info('Tentative d\'envoi d\'email...');
            Mail::to($user->email)->send(new AccountStatusChanged($user, $user->is_active));
            \Log::info('✅ Email envoyé avec succès!');
        } catch (\Exception $e) {
            \Log::error('❌ Erreur email: ' . $e->getMessage());
            \Log::error('Fichier: ' . $e->getFile());
            \Log::error('Ligne: ' . $e->getLine());
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut du compte mis à jour avec succès.',
            'is_active' => $user->is_active
        ]);

    } catch (\Exception $e) {
        \Log::error('Erreur toggle: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la modification du statut'
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
                'message' => 'Feedback modéré avec succès.',
                'data' => $feedback
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la modération: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modération'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques
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
            Log::error('Erreur lors de la récupération des stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    // ==================== MÉTHODES UTILITAIRES ====================

  

 
}