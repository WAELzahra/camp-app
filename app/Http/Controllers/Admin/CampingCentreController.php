<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CampingCentre;
use App\Models\Camping_zones;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\ProfileCentre;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CampingCentreController extends Controller
{
    /**
     * List all centres with partner/non-partner classification.
     *
     * A centre is a PARTNER when it has an associated user (role=centre)
     * or a linked profile_centre record.  All other centres are NON-PARTNER.
     *
     * On every call we auto-sync users with role_id=3 into camping_centres so
     * that registered centre accounts always appear here without a schema change.
     */
    public function index()
    {
        // ── Auto-sync ACTIVE registered centre users that have no camping_centre yet ──
        // Only sync is_active=1 users — inactive accounts are not yet approved partners.
        $linkedUserIds = CampingCentre::whereNotNull('user_id')->pluck('user_id');

        User::where('role_id', 3)
            ->where('is_active', 1)
            ->whereNotIn('id', $linkedUserIds)
            ->with(['profile.profileCentre'])
            ->get()
            ->each(function (User $user) {
                $pc = $user->profile?->profileCentre;
                CampingCentre::create([
                    'nom'               => $pc?->name ?? trim($user->first_name . ' ' . $user->last_name),
                    'type'              => 'centre',
                    'adresse'           => $user->adresse ?? $user->profile?->address,
                    'lat'               => (float) ($pc?->latitude  ?? 0),
                    'lng'               => (float) ($pc?->longitude ?? 0),
                    'status'            => $pc ? (bool) $pc->disponibilite : false,
                    'validation_status' => 'approved',
                    'user_id'           => $user->id,
                    'profile_centre_id' => $pc?->id,
                ]);
            });

        // ── Return all centres with is_partner flag ──────────────────────────
        // A centre is a partner ONLY when it has an associated user AND that user is active.
        $centres = CampingCentre::with(['zones', 'user', 'profileCentre'])
            ->get()
            ->map(function (CampingCentre $c) {
                $c->is_partner = (! is_null($c->user_id) || ! is_null($c->profile_centre_id))
                                 && ($c->user?->is_active == 1);
                return $c;
            });

        return response()->json([
            'status'  => 'success',
            'centres' => $centres,
        ]);
    }

    /**
     * Ajouter un centre (admin peut créer un centre "manuel")
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom'               => 'required|string|max:255',
            'adresse'           => 'required|string|max:255',
            'lat'               => 'required|numeric',
            'lng'               => 'required|numeric',
            'type'              => 'nullable|string',
            'description'       => 'nullable|string',
            'status'            => 'nullable|boolean',
            'validation_status' => 'nullable|in:pending,approved,rejected',
            'user_id'           => 'nullable|exists:users,id',
            // Photos : tableau de fichiers images
            'photos'            => 'nullable|array',
            'photos.*'          => 'file|image|max:4096',
            'cover_index'       => 'nullable|integer|min:0',
        ]);

        $centre = CampingCentre::create([
            'nom'               => $request->nom,
            'adresse'           => $request->adresse,
            'lat'               => $request->lat,
            'lng'               => $request->lng,
            'type'              => $request->type ?? 'centre',
            'description'       => $request->description,
            'status'            => $request->boolean('status', false),
            'validation_status' => $request->input('validation_status', 'pending'),
            'user_id'           => $request->user_id ?? null,
        ]);

        // Stocker les photos dans la table photos
        $coverIndex = (int) $request->input('cover_index', 0);
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $file) {
                $path     = $file->store('centres/photos', 'public');
                $isCover  = ($index === $coverIndex);

                $photo = Photo::create([
                    'path_to_img'       => $path,
                    'camping_centre_id' => $centre->id,
                    'is_cover'          => $isCover,
                    'order'             => $index,
                ]);

                // La première photo cover devient l'image principale du centre
                if ($isCover) {
                    $centre->update(['image' => $path]);
                }
            }
        }

        return response()->json([
            'message' => 'Centre créé avec succès',
            'centre'  => $centre->load('photos'),
        ], 201);
    }

    // Mettre à jour un centre
    public function update(Request $request, $id)
{
    // Récupérer le centre
    $centre = CampingCentre::findOrFail($id);

    // Validation des données reçues
    $data = $request->validate([
        'nom'               => 'sometimes|string|max:255',
        'adresse'           => 'sometimes|string|max:255',
        'lat'               => 'sometimes|numeric',
        'lng'               => 'sometimes|numeric',
        'type'              => 'sometimes|string',
        'image'             => 'nullable|image|max:2048',
        'description'       => 'nullable|string',
        'status'            => 'sometimes|boolean',
        'validation_status' => 'nullable|in:pending,approved,rejected',
    ]);

    // Gestion de l'image si upload
    if ($request->hasFile('image')) {
        // Supprimer ancienne image si elle existe
        if ($centre->image) {
            Storage::disk('public')->delete($centre->image);
        }
        $data['image'] = $request->file('image')->store('centres', 'public');
    }

    // Mettre à jour le centre
    $centre->update($data);

    // Si le centre est inscrit (lié à un user), mettre à jour les infos dans user/profile
    if ($centre->user_id) {
        $user = $centre->user;
        if ($request->filled('nom')) $user->name = $request->nom;
        if ($request->filled('adresse')) $user->adresse = $request->adresse;
        $user->save();

        // Mettre à jour le profile centre si exists
        if ($centre->profileCentre) {
            $profile = $centre->profileCentre;
            if ($request->filled('description')) $profile->description = $request->description;
            if ($request->filled('type')) $profile->type = $request->type;
            $profile->save();
        }
    }

    return response()->json([
        'message' => 'Centre mis à jour avec succès',
        'centre' => $centre
    ]);
}

    /**
     * Associer plusieurs zones à un centre
     */
    public function assignZones(Request $request, $centreId)
    {
        $request->validate([
            'zone_ids' => 'required|array',
        ]);

        $centre = CampingCentre::findOrFail($centreId);

        foreach ($request->zone_ids as $zoneId) {
            $zone = Camping_zones::find($zoneId);
            if ($zone) {
                $zone->centre_id = $centre->id;
                $zone->save();
            }
        }

        return response()->json([
            'message' => 'Zones associées au centre avec succès',
            'centre' => $centre->load('zones')
        ]);
    }

    /**
     * Récupérer les infos complètes d'un centre
     * Inclut user, profile et profile_centre si existants
     */
    public function show($id)
    {
        $centre = CampingCentre::with([
            'user.profile',         // user inscrit
            'profileCentre',        // infos supplémentaires
            'zones',                // zones associées
            'photos',               // photos du centre
        ])->findOrFail($id);

        $centreData = $centre->toArray();
        $centreData['photos'] = $centre->photos->map(fn($p) => [
            'id'       => $p->id,
            'url'      => asset('storage/' . $p->path_to_img),
            'path'     => $p->path_to_img,
            'is_cover' => (bool) $p->is_cover,
            'order'    => $p->order,
        ])->values()->toArray();

        return response()->json([
            'status' => 'success',
            'centre' => $centreData,
        ]);
    }

    /**
     * Lister tous les centres inscrits (user + profile + profile_centre)
     */
    public function registeredCentres()
    {
        $centres = CampingCentre::whereNotNull('user_id')
            ->with(['user.profile', 'profileCentre', 'zones'])
            ->get();

        return response()->json([
            'status' => 'success',
            'centres' => $centres
        ]);
    }

    // Statistiques sur les centres
    public function stats()
    {
        return response()->json([
            'total_centres'   => CampingCentre::count(),
            'public_centres'  => CampingCentre::where('status', true)->count(),
            'private_centres' => CampingCentre::where('status', false)->count(),
            'zones_per_centre' => CampingCentre::withCount('zones')->get(),
        ]);
    }



    // Activation / désactivation d’un centre
    public function toggleStatus(Request $request, $id)
    {
        $centre = CampingCentre::findOrFail($id);

        // Si "status" est envoyé dans la requête => on force la valeur
        if ($request->has('status')) {
            $centre->status = (bool) $request->status;
        } else {
            // Sinon on inverse (toggle classique)
            $centre->status = !$centre->status;
        }

        $centre->save();

        return response()->json([
            'message' => $centre->status ? 'Centre rendu public' : 'Centre rendu privé',
            'centre' => $centre
        ]);
    }



    // search centres
    public function search(Request $request)
    {
        $query = CampingCentre::query();

        if ($request->filled('nom')) {
            $query->where('nom', 'LIKE', "%{$request->nom}%");
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->with('zones')->paginate(10));
    }

    // centres à proximité
    public function nearby(Request $request)
{
    $request->validate([
        'lat' => 'required|numeric',
        'lng' => 'required|numeric',
        'radius' => 'nullable|numeric'
    ]);

    $radius = $request->radius ?? 10; // km

    $centres = CampingCentre::select('*')
        ->selectRaw(
            '(6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance',
            [$request->lat, $request->lng, $request->lat]
        )
        ->having('distance', '<=', $radius)
        ->orderBy('distance')
        ->get();

    return response()->json($centres);
    }   

    // Suggestions automatiques pour les zones
    // Afficher à l’admin les zones non associées à un centre + Proposer automatiquement un centre proche pour association
    public function suggestZones()
    {
        $zones = Camping_zones::whereNull('centre_id')->get();

        $suggestions = $zones->map(function($zone){
            $nearestCentre = CampingCentre::select('*')
                ->orderByRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) ASC',
                    [$zone->lat, $zone->lng, $zone->lat]
                )->first();

            return [
                'zone' => $zone,
                'suggested_centre' => $nearestCentre
            ];
        });

        return response()->json($suggestions);
    }

    /**
     * DELETE /admin/centres/{id}
     */
    public function destroy($id)
    {
        $centre = CampingCentre::with('photos')->findOrFail($id);

        // Supprimer les fichiers photos du stockage
        foreach ($centre->photos as $photo) {
            Storage::disk('public')->delete($photo->path_to_img);
        }
        // Supprimer l'image principale si elle existe
        if ($centre->image) {
            Storage::disk('public')->delete($centre->image);
        }

        $centre->delete();

        return response()->json(['status' => 'success', 'message' => 'Centre supprimé avec succès.']);
    }

    /**
     * POST /admin/centres/{id}/unlink-user
     * Délier un centre de son utilisateur.
     */
    public function unlinkUser($id)
    {
        $centre = CampingCentre::findOrFail($id);
        $centre->update(['user_id' => null, 'validation_status' => 'pending']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur dissocié du centre.',
            'centre'  => $centre->fresh(['zones', 'photos']),
        ]);
    }

    /**
     * POST /admin/centres/{id}/photos
     * Ajouter des photos à un centre existant.
     */
    public function addPhotos(Request $request, $id)
    {
        $request->validate([
            'photos'      => 'required|array',
            'photos.*'    => 'file|image|max:4096',
            'cover_index' => 'nullable|integer',
        ]);

        $centre     = CampingCentre::findOrFail($id);
        $coverIndex = (int) $request->input('cover_index', -1);
        $offset     = $centre->photos()->count();
        $hasImage   = !is_null($centre->image);

        foreach ($request->file('photos') as $index => $file) {
            $path    = $file->store('centres/photos', 'public');
            $isCover = ($index === $coverIndex);

            Photo::create([
                'path_to_img'       => $path,
                'camping_centre_id' => $centre->id,
                'is_cover'          => $isCover,
                'order'             => $offset + $index,
            ]);

            if ($isCover) {
                $centre->update(['image' => $path]);
                $hasImage = true;
            }
        }

        // Auto-promote first photo as cover if the centre still has no image
        if (!$hasImage) {
            $firstPhoto = $centre->photos()->orderBy('order')->first();
            if ($firstPhoto) {
                $firstPhoto->update(['is_cover' => true]);
                $centre->update(['image' => $firstPhoto->path_to_img]);
            }
        }

        return response()->json([
            'status' => 'success',
            'photos' => $centre->fresh('photos')->photos->map(fn($p) => [
                'id'         => $p->id,
                'url'        => asset('storage/' . $p->path_to_img),
                'path'       => $p->path_to_img,
                'is_cover'   => (bool) $p->is_cover,
                'order'      => $p->order,
            ]),
        ]);
    }

    /**
     * DELETE /admin/centres/{centreId}/photos/{photoId}
     */
    public function deletePhoto($centreId, $photoId)
    {
        $centre = CampingCentre::findOrFail($centreId);
        $photo  = Photo::where('camping_centre_id', $centreId)->findOrFail($photoId);

        Storage::disk('public')->delete($photo->path_to_img);

        // Si c'était la photo cover, retirer l'image du centre
        if ($photo->is_cover) {
            $centre->update(['image' => null]);
        }

        $photo->delete();

        return response()->json(['status' => 'success', 'message' => 'Photo supprimée.']);
    }

    /**
     * PATCH /admin/centres/{centreId}/photos/{photoId}/cover
     * Définir une photo comme couverture principale.
     */
    public function setCoverPhoto($centreId, $photoId)
    {
        $centre = CampingCentre::findOrFail($centreId);

        // Retirer l'ancien cover
        Photo::where('camping_centre_id', $centreId)->update(['is_cover' => false]);

        $photo = Photo::where('camping_centre_id', $centreId)->findOrFail($photoId);
        $photo->update(['is_cover' => true]);
        $centre->update(['image' => $photo->path_to_img]);

        return response()->json(['status' => 'success', 'message' => 'Photo de couverture mise à jour.']);
    }

    /**
     * POST /admin/centres/{id}/link-user
     * Lier un centre existant à un utilisateur et fusionner avec son profil centre.
     */
    public function linkUser(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $centre = CampingCentre::with('profileCentre')->findOrFail($id);
        $user   = User::with('profile')->findOrFail($request->user_id);

        // 1. Lier l'utilisateur au centre
        $centre->user_id = $user->id;

        // 2. Récupérer ou créer le profil de l'utilisateur
        $profile = $user->profile;
        if (!$profile) {
            $profile = Profile::create(['user_id' => $user->id]);
        }

        // 3. Récupérer ou créer le profile_centre lié à ce profil
        $profileCentre = ProfileCentre::firstOrCreate(
            ['profile_id' => $profile->id],
            ['disponibilite' => true]
        );

        // 4. Lier le profile_centre au centre
        $centre->profile_centre_id = $profileCentre->id;
        $centre->validation_status = 'approved';
        $centre->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Centre lié au profil utilisateur avec succès.',
            'centre'  => $centre->fresh(['user', 'profileCentre', 'zones', 'photos']),
        ]);
    }

    /**
     * GET /admin/centres/search-users?q=xxx
     * Recherche rapide d'utilisateurs pour l'assignation d'un centre.
     */
    public function searchUsers(Request $request)
    {
        $q = trim($request->query('q', ''));

        $users = User::with('role')
            ->when($q !== '', fn($query) =>
                $query->where(function ($q2) use ($q) {
                    $q2->where('first_name', 'like', "%{$q}%")
                       ->orWhere('last_name',  'like', "%{$q}%")
                       ->orWhere('email',       'like', "%{$q}%");
                })
            )
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'first_name' => $u->first_name,
                'last_name'  => $u->last_name,
                'email'      => $u->email,
                'avatar'     => $u->avatar,
                'role'       => $u->role?->name,
            ]);

        return response()->json(['status' => 'success', 'data' => $users]);
    }

}