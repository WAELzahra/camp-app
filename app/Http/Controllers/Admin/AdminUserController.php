<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use App\Models\ProfileGuide;
use App\Models\ProfileCentre;
use App\Models\ProfileGroupe;
use App\Models\ProfileFournisseur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountStatusChanged;
use App\Models\Feedbacks; 
use App\Models\Role;
use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

class AdminUserController extends Controller
{
    
// Affiche la liste des utilisateurs avec option de filtrage par rôle
public function index(Request $request)
{
    $role = $request->query('role'); // Récupérer le rôle passé en paramètre GET

    $query = User::with([
        'profile.profileGuide',
        'profile.profileCentre',
        'profile.profileGroupe',
        'profile.profileFournisseur'
    ])->where('id', '!=', auth()->id());

    if ($role) {
        $query->whereHas('profile', function ($q) use ($role) {
            $q->where('type', $role);
        });
    }

    $users = $query->paginate(15);

    return response()->json($users);
}

    // Affiche un utilisateur complet
    public function show($id)
    {
        $user = User::with([
            'profile.profileGuide',
            'profile.profileCentre',
            'profile.profileGroupe',
            'profile.profileFournisseur'
        ])->findOrFail($id);

        return response()->json($user);
    }

    // Met à jour un utilisateur et ses profils selon type
    public function update(Request $request, $id)
    {
        $user = User::with('profile')->findOrFail($id);

        // Validation User
        $validatedUser = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:6|confirmed',
            'is_active' => 'sometimes|boolean',
            // autres champs de user si besoin
        ]);

        // Update user, hash password si modifié
        if (isset($validatedUser['password'])) {
            $validatedUser['password'] = bcrypt($validatedUser['password']);
        }
        $user->update($validatedUser);

        // Si profile lié
        if ($user->profile) {
            // Validation Profile (commune)
            $validatedProfile = $request->validate([
                'bio' => 'sometimes|string|nullable',
                'cover_image' => 'sometimes|string|nullable',
                'immatricule' => 'sometimes|string|nullable',
                'adresse' => 'sometimes|string|nullable',
                'date_naissance' => 'sometimes|date|nullable',
                'sexe' => 'sometimes|string|in:male,female,other|nullable',
                'type' => ['sometimes', Rule::in(['campeur', 'guide', 'centre', 'groupe', 'fournisseur'])],
            ]);

            $user->profile->update($validatedProfile);

            // Mettre à jour la table fille selon le type
            switch ($user->profile->type) {
                case 'guide':
                    $profileGuide = $user->profile->profileGuide ?? new ProfileGuide(['profile_id' => $user->profile->id]);
                    $validatedGuide = $request->validate([
                        'langue' => 'sometimes|string|nullable',
                        'experience' => 'sometimes|string|nullable',
                        'tarif' => 'sometimes|numeric|nullable',
                        'zone_travail' => 'sometimes|string|nullable',
                    ]);
                    $profileGuide->fill($validatedGuide);
                    $profileGuide->profile_id = $user->profile->id;
                    $profileGuide->save();
                    break;

                case 'centre':
                    $profileCentre = $user->profile->profileCentre ?? new ProfileCentre(['profile_id' => $user->profile->id]);
                    $validatedCentre = $request->validate([
                        'capacite' => 'sometimes|integer|nullable',
                        'service_offrant' => 'sometimes|string|nullable',
                        'document_legal' => 'sometimes|string|nullable',
                        'disponibilite' => 'sometimes|string|nullable',
                        'id_annonce' => 'sometimes|integer|nullable',
                        'id_album_photo' => 'sometimes|integer|nullable',
                    ]);
                    $profileCentre->fill($validatedCentre);
                    $profileCentre->profile_id = $user->profile->id;
                    $profileCentre->save();
                    break;

                case 'groupe':
                    $profileGroupe = $user->profile->profileGroupe ?? new ProfileGroupe(['profile_id' => $user->profile->id]);
                    $validatedGroupe = $request->validate([
                        'nom_groupe' => 'sometimes|string|nullable',
                        'id_album_photo' => 'sometimes|integer|nullable',
                        'id_participant' => 'sometimes|integer|nullable',
                        'id_annonce' => 'sometimes|integer|nullable',
                        'cin_responsable' => 'sometimes|string|nullable',
                    ]);
                    $profileGroupe->fill($validatedGroupe);
                    $profileGroupe->profile_id = $user->profile->id;
                    $profileGroupe->save();
                    break;

                case 'fournisseur':
                    $profileFournisseur = $user->profile->profileFournisseur ?? new ProfileFournisseur(['profile_id' => $user->profile->id]);
                    $validatedFournisseur = $request->validate([
                        'intervalle_prix' => 'sometimes|string|nullable',
                        'product_category' => 'sometimes|string|nullable',
                    ]);
                    $profileFournisseur->fill($validatedFournisseur);
                    $profileFournisseur->profile_id = $user->profile->id;
                    $profileFournisseur->save();
                    break;

                case 'campeur':
                default:
                    // Pas de table fille pour campeur
                    break;
            }
        }

        return response()->json(['message' => 'Utilisateur et profil mis à jour avec succès']);
    }

    // Supprimer un utilisateur + profiles associés
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->profile) {
            switch ($user->profile->type) {
                case 'guide':
                    $user->profile->profileGuide?->delete();
                    break;
                case 'centre':
                    $user->profile->profileCentre?->delete();
                    break;
                case 'groupe':
                    $user->profile->profileGroupe?->delete();
                    break;
                case 'fournisseur':
                    $user->profile->profileFournisseur?->delete();
                    break;
                case 'campeur':
                default:
                    // Rien à supprimer
                    break;
            }
            $user->profile->delete();
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé avec succès']);
    }

// Activer/Désactiver un utilisateur
public function toggleActivation($id)
{
    $user = User::findOrFail($id);

    // Interdire à l'admin de se désactiver lui-même
    if (auth()->id() == $user->id) {
        return response()->json(['message' => 'Vous ne pouvez pas désactiver votre propre compte.'], 403);
    }

    $user->is_active = !$user->is_active;
    $user->save();

    // Envoyer un mail au propriétaire du compte
    Mail::to($user->email)->send(new AccountStatusChanged($user->is_active));

    return response()->json([
        'message' => 'Statut du compte mis à jour avec succès.',
        'is_active' => $user->is_active
    ]);
}


// Modérer un feedback
public function moderate(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:approved,rejected',
        'response' => 'nullable|string|max:1000',
    ]);

    $feedback = Feedbacks::findOrFail($id);
    $feedback->status = $request->status;
    $feedback->response = $request->response ?? null;
    $feedback->save();

    return response()->json(['message' => 'Feedback modéré avec succès.']);
}



}
