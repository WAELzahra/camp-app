<?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use App\Models\Profile;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\DB;

// class ProfileController extends Controller
// {


//     public function show($id)
// {
//     $profile = Profile::findOrFail($id);

//     // Récupérer les données spécifiques selon le type
//     $details = null;

//     switch ($profile->type) {
//         case 'guide':
//             $details = DB::table('profile_guides')->where('profile_id', $profile->id)->first();
//             break;

//         case 'centre':
//             $details = DB::table('profile_centres')->where('profile_id', $profile->id)->first();
//             break;

//         case 'groupe':
//             $details = DB::table('profile_groupes')->where('profile_id', $profile->id)->first();
//             break;

//         case 'fournisseur':
//             $details = DB::table('profile_fournisseurs')->where('profile_id', $profile->id)->first();
//             break;
//     }

//     return response()->json([
//         'profile' => $profile,
//         'details' => $details,
//     ]);
// }

//     public function update(Request $request, $id)
//     {
//         $profile = Profile::findOrFail($id);
//         $user = $profile->user;

//         $validated = $request->validate([
//             'bio' => 'nullable|string|max:1000',
//             'cover_image' => 'nullable|string',
//             'adresse' => 'nullable|string|max:255',
//             'immatricule' => 'nullable|string|max:50',
//             // Champs enfants selon le type
//             'experience' => 'nullable|string',
//             'tarif' => 'nullable|numeric',
//             'zone_travail' => 'nullable|string|max:255',
//             'capacite' => 'nullable|integer',
//             'services_offerts' => 'nullable|string',
//             'document_legal' => 'nullable|string',
//             'disponibilite' => 'nullable|boolean',
//             'nom_groupe' => 'nullable|string|max:255',
//             'cin_responsable' => 'nullable|string|max:20',
//             'intervale_prix' => 'nullable|string',
//             'product_category' => 'nullable|string',
//         ]);

//         // Mise à jour du profil principal
//         $profile->update([
//             'bio' => $validated['bio'] ?? $profile->bio,
//             'cover_image' => $validated['cover_image'] ?? $profile->cover_image,
//             'adresse' => $validated['adresse'] ?? $profile->adresse,
//             'immatricule' => $validated['immatricule'] ?? $profile->immatricule,
//         ]);

//         // Mise à jour table fille selon type
//         switch ($profile->type) {
//             case 'guide':
//                 DB::table('profile_guides')
//                     ->where('profile_id', $profile->id)
//                     ->update([
//                         'experience' => $validated['experience'] ?? null,
//                         'tarif' => $validated['tarif'] ?? null,
//                         'zone_travail' => $validated['zone_travail'] ?? null,
//                         'updated_at' => now(),
//                     ]);
//                 break;

//             case 'centre':
//                 DB::table('profile_centres')
//                     ->where('profile_id', $profile->id)
//                     ->update([
//                         'capacite' => $validated['capacite'] ?? null,
//                         'services_offerts' => $validated['services_offerts'] ?? null,
//                         'document_legal' => $validated['document_legal'] ?? null,
//                         'disponibilite' => $validated['disponibilite'] ?? true,
//                         'updated_at' => now(),
//                     ]);
//                 break;

//             case 'groupe':
//                 DB::table('profile_groupes')
//                     ->where('profile_id', $profile->id)
//                     ->update([
//                         'nom_groupe' => $validated['nom_groupe'] ?? null,
//                         'cin_responsable' => $validated['cin_responsable'] ?? null,
//                         'updated_at' => now(),
//                     ]);
//                 break;

//             case 'fournisseur':
//                 DB::table('profile_fournisseurs')
//                     ->where('profile_id', $profile->id)
//                     ->update([
//                         'intervale_prix' => $validated['intervale_prix'] ?? null,
//                         'product_category' => $validated['product_category'] ?? null,
//                         'updated_at' => now(),
//                     ]);
//                 break;
//         }

//         return response()->json([
//             'message' => 'Profil mis à jour avec succès.',
//             'profile' => $profile,
//         ]);
//     }
// }




namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Profile;
use App\Models\ProfileGuide;
use App\Models\ProfileCentre;
use App\Models\ProfileGroupe;
use App\Models\ProfileFournisseur;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $profile = $user->profile;

        $data = [
            'user' => $user,
            'profile' => $profile,
        ];

        // Ajouter les données de la table enfant si elle existe selon le type
        switch ($profile->type) {
            case 'guide':
                $data['details'] = $profile->profileGuide;
                break;
            case 'centre':
                $data['details'] = $profile->profileCentre;
                break;
            case 'groupe':
                $data['details'] = $profile->profileGroupe;
                break;
            case 'fournisseur':
                $data['details'] = $profile->profileFournisseur;
                break;
            default: // campeur ou autre
                $data['details'] = null;
        }

        return response()->json($data);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $profile = $user->profile;

        // Mise à jour des données utilisateur
        $user->update($request->only([
            'name',
            'email',
            'phone_number',
            'adresse',
            'ville',
            'date_naissance',
            'sexe',
            'langue',
        ]));

        // Mise à jour du profil
        $profile->update($request->only([
            'bio',
            'cover_image',
            'immatricule',
        ]));

        // Mise à jour de la table enfant si elle existe
        switch ($profile->type) {
            case 'guide':
                $profile->profileGuide?->update($request->only([
                    'experience',
                    'tarif',
                    'zone_travail',
                ]));
                break;

            case 'centre':
                $profile->profileCentre?->update($request->only([
                    'capacite',
                    'services_offerts',
                    'document_legal',
                    'disponibilite',
                    'id_annonce',
                    'id_album_photo',
                ]));
                break;

            case 'groupe':
                $profile->profileGroupe?->update($request->only([
                    'nom_groupe',
                    'id_album_photo',
                    'id_annonce',
                    'cin_responsable',
                ]));
                break;

            case 'fournisseur':
                $profile->profileFournisseur?->update($request->only([
                    'intervale_prix',
                    'product_category',
                ]));
                break;
        }

        return response()->json([
            'message' => 'Profil mis à jour avec succès'
        ]);
    }
}
