<?php


namespace App\Http\Controllers\profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Profile;
use App\Models\ProfileGuide;
use App\Models\ProfileCentre;
use App\Models\ProfileGroupe;
use App\Models\ProfileFournisseur;
use App\Models\Album;
use App\Models\Photos;
use App\Models\User;
use App\Http\Controllers\Controller;
class ProfileController extends Controller
{
    

    public function show()
{
    $user = Auth::user();
    $profile = $user->profile;

    $data = [
        'user' => $user,
        'profile' => $profile,
        'details' => null,
    ];

    switch ($profile->type) {
        case 'guide':
            $data['details'] = $profile->profileGuide;
            break;
        case 'centre':
            $data['details'] = $profile->profileCentre;
            break;
        case 'groupe':
            $data['details'] = $profile->profileGroupe;

            // Inclure les feedbacks du groupe (s’il en est un)
            $feedbacks = \App\Models\Feedbacks::with('user')
                ->where('target_id', $user->id)
                ->where('type', 'groupe')
                ->where('status', 'approved')
                ->latest()
                ->take(5)
                ->get();

            $average = \App\Models\Feedbacks::where('target_id', $user->id)
                ->where('type', 'groupe')
                ->where('status', 'approved')
                ->avg('note');

            $count = \App\Models\Feedbacks::where('target_id', $user->id)
                ->where('type', 'groupe')
                ->where('status', 'approved')
                ->count();

            $data['feedback_summary'] = [
                'average_note' => round($average, 2),
                'feedback_count' => $count,
                'latest_feedbacks' => $feedbacks,
            ];
            break;
        case 'fournisseur':
            $data['details'] = $profile->profileFournisseur;
            break;
        default:
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

    public function updateAvatar(Request $request)
{
    $request->validate([
        'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
    ]);

    $user = Auth::user();

    // Supprimer l'ancien avatar si existant
    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
        Storage::disk('public')->delete($user->avatar);
    }

    // Stocker le nouveau fichier
    $avatarPath = $request->file('avatar')->store('avatars', 'public');

    // Mise à jour du champ avatar
    $user->update(['avatar' => $avatarPath]);

    return response()->json([
        'message' => 'Avatar mis à jour avec succès.',
        'avatar_url' => asset('storage/' . $avatarPath),
    ]);
}


public function storeOrUpdateProfilePhotos(Request $request)
{
    $user = Auth::user();

    // Bloquer les rôles non autorisés
    if (in_array($user->role->name, ['admin', 'campeur'])) {
        return response()->json(['message' => 'Vous n\'êtes pas autorisé à modifier les photos de profil.'], 403);
    }

    // Validation des images
    $request->validate([
        'photos.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
    ]);

    // Récupérer ou créer l'album
    $album = Album::firstOrCreate(
        ['titre' => "Album de {$user->name}"],
        ['description' => 'Album personnel de profil']
    );

    // Supprimer les anciennes photos de cet album
    $oldPhotos = $album->photos;

    foreach ($oldPhotos as $oldPhoto) {
        // Supprimer le fichier physique
        if (Storage::disk('public')->exists($oldPhoto->path_to_img)) {
            Storage::disk('public')->delete($oldPhoto->path_to_img);
        }
        $oldPhoto->delete();
    }

    // Ajouter les nouvelles photos
    $newPhotoPaths = [];

    foreach ($request->file('photos') as $photo) {
        $path = $photo->store('photos', 'public');

        Photos::create([
            'path_to_img' => $path,
            'user_id' => $user->id,
            'album_id' => $album->id,
        ]);

        $newPhotoPaths[] = asset('storage/' . $path);
    }

    return response()->json([
        'message' => 'Album de profil mis à jour avec succès.',
        'photos_urls' => $newPhotoPaths
    ]);
}


    // Déterminer la saison actuelle
    private function getCurrentSeason()
    {
        $month = date('n');
        if (in_array($month, [12, 1, 2])) return 'hiver';
        if (in_array($month, [3, 4, 5])) return 'printemps';
        if (in_array($month, [6, 7, 8])) return 'été';
        return 'automne';
    }

    // Récupérer les préférences utilisateur
    private function getUserPreferences($userId)
    {
        $user = User::findOrFail($userId);
        return $user->preferences ? json_decode($user->preferences, true) : [];
    }

    // Déterminer la région de référence
    private function getUserRegion($userId)
    {
        $user = User::findOrFail($userId);

        // 1️⃣ Région dans les préférences
        $preferences = $this->getUserPreferences($userId);
        if (!empty($preferences['region'])) {
            return $preferences['region'];
        }

        // 2️⃣ Région dans le profil
        if (!empty($user->ville)) {
            return $user->ville;
        }

        // 3️⃣ Valeur par défaut
        return 'Tunisie';
    }




}
