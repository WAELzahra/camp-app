<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CampingCentre;
use App\Models\Camping_zones;
use App\Models\CentreClaim;
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
        // Skip users with a pending claim — their camping_centre will be created properly
        // when the claim is approved, avoiding duplicate entries in the admin list.
        $linkedUserIds       = CampingCentre::whereNotNull('user_id')->pluck('user_id');
        $pendingClaimUserIds = CentreClaim::where('status', 'pending')->pluck('user_id');

        User::where('role_id', 3)
            ->where('is_active', 1)
            ->whereNotIn('id', $linkedUserIds)
            ->whereNotIn('id', $pendingClaimUserIds)
            ->with(['profile'])
            ->get()
            ->each(function (User $user) {
                // ✅ Get or create profile for this user
                $profile = $user->profile;
                if (!$profile) {
                    $profile = \App\Models\Profile::create([
                        'user_id' => $user->id,
                        'type' => 'centre',
                        'address' => $user->adresse,
                    ]);
                }

                // ✅ Get the profile_centre that ACTUALLY belongs to THIS profile
                $pc = \App\Models\ProfileCentre::where('profile_id', $profile->id)->first();
                
                // If no profile_centre exists for this profile, create one
                if (!$pc) {
                    $pc = \App\Models\ProfileCentre::create([
                        'profile_id' => $profile->id,
                        'name' => trim($user->first_name . ' ' . $user->last_name) . ' Center',
                        'latitude' => 0,
                        'longitude' => 0,
                        'disponibilite' => false,
                    ]);
                }

                // ✅ Now create the camping_centre with the CORRECT profile_centre_id
                CampingCentre::create([
                    'nom'               => $pc->name ?? trim($user->first_name . ' ' . $user->last_name),
                    'type'              => 'centre',
                    'adresse'           => $profile->address ?? $user->adresse,
                    'lat'               => (float) ($pc->latitude ?? 0),
                    'lng'               => (float) ($pc->longitude ?? 0),
                    'status'            => (bool) ($pc->disponibilite ?? false),
                    'validation_status' => 'approved',
                    'is_partner'        => true,
                    'user_id'           => $user->id,
                    'profile_centre_id' => $pc->id,
                ]);
            });

        // ── Return only type='centre' rows — excludes hors_centre and anything else ──
        $centres = CampingCentre::with(['user', 'profileCentre'])
            ->where('type', 'centre')
            ->get();

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
            'telephone'         => 'nullable|string|max:20',
            'lat'               => 'required|numeric',
            'lng'               => 'required|numeric',
            'type'              => 'nullable|string',
            'description'       => 'nullable|string',
            'status'            => 'nullable|boolean',
            'is_partner'        => 'nullable|boolean',
            'validation_status' => 'nullable|in:pending,approved,rejected',
            'user_id'           => 'nullable|exists:users,id',
            'photos'            => 'nullable|array',
            'photos.*'          => 'file|image|max:5120',
            'cover_index'       => 'nullable|integer|min:0',
        ]);

        $centre = CampingCentre::create([
            'nom'               => $request->nom,
            'adresse'           => $request->adresse,
            'telephone'         => $request->telephone ?? null,
            'lat'               => $request->lat,
            'lng'               => $request->lng,
            'type'              => $request->type ?? 'centre',
            'description'       => $request->description,
            'status'            => $request->boolean('status', false),
            'is_partner'        => $request->boolean('is_partner', false),
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
        'telephone'         => 'nullable|string|max:20',
        'lat'               => 'sometimes|numeric',
        'lng'               => 'sometimes|numeric',
        'type'              => 'sometimes|string',
        'image'             => 'nullable|image|max:5120',
        'description'       => 'nullable|string',
        'status'            => 'sometimes|boolean',
        'is_partner'        => 'sometimes|boolean',
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
            'user.profile',
            'profileCentre',
            'photos',
        ])->findOrFail($id);

        $photos = $centre->photos->map(fn($p) => [
            'id'       => $p->id,
            'url'      => storage_url($p->path_to_img),
            'path'     => $p->path_to_img,
            'is_cover' => (bool) $p->is_cover,
            'order'    => $p->order,
        ])->values()->toArray();

        // Build profileCentre payload with camelCase key so the frontend type
        // (AdminProfileCentre) can access it directly without any transformation.
        $profileCentrePayload = null;
        if ($centre->profileCentre) {
            $pc = $centre->profileCentre;

            $services = $pc->centerServices()
                ->with('serviceCategory')
                ->orderBy('is_standard', 'desc')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($s) => [
                    'id'           => $s->id,
                    'name'         => $s->name ?? $s->serviceCategory?->name ?? 'Service',
                    'description'  => $s->description ?? $s->serviceCategory?->description ?? '',
                    'price'        => (float) $s->price,
                    'unit'         => $s->unit ?? $s->serviceCategory?->unit ?? '',
                    'is_standard'  => (bool) $s->is_standard,
                    'is_available' => (bool) $s->is_available,
                ])->values()->toArray();

            $equipment = $pc->equipment()
                ->get()
                ->map(fn($e) => [
                    'id'           => $e->id,
                    'type'         => $e->type,
                    'is_available' => (bool) $e->is_available,
                    'notes'        => $e->notes,
                ])->values()->toArray();

            $profileCentrePayload = [
                'id'              => $pc->id,
                'name'            => $pc->name,
                'description'     => $pc->profile?->bio ?? null,
                'price_per_night' => $pc->price_per_night !== null ? (float) $pc->price_per_night : null,
                'category'        => $pc->category,
                'capacite'        => $pc->capacite,
                'disponibilite'   => (bool) $pc->disponibilite,
                'contact_email'   => $pc->contact_email,
                'contact_phone'   => $pc->contact_phone,
                'manager_name'    => $pc->manager_name,
                'established_date'=> $pc->established_date?->format('Y-m-d'),
                'latitude'        => $pc->latitude !== null ? (float) $pc->latitude : null,
                'longitude'       => $pc->longitude !== null ? (float) $pc->longitude : null,
                'services'        => $services,
                'equipment'       => $equipment,
            ];
        }

        return response()->json([
            'status' => 'success',
            'centre' => [
                'id'                => $centre->id,
                'nom'               => $centre->nom,
                'type'              => $centre->type,
                'description'       => $centre->description,
                'adresse'           => $centre->adresse,
                'telephone'         => $centre->telephone,
                'lat'               => $centre->lat,
                'lng'               => $centre->lng,
                'image'             => $centre->image,
                'status'            => (bool) $centre->status,
                'is_partner'        => (bool) $centre->is_partner,
                'validation_status' => $centre->validation_status,
                'user_id'           => $centre->user_id,
                'profile_centre_id' => $centre->profile_centre_id,
                'created_at'        => $centre->created_at,
                'updated_at'        => $centre->updated_at,
                'photos'            => $photos,
                'user'              => $centre->user ? [
                    'id'         => $centre->user->id,
                    'first_name' => $centre->user->first_name,
                    'last_name'  => $centre->user->last_name,
                    'email'      => $centre->user->email,
                    'is_active'  => $centre->user->is_active,
                    'avatar'     => $centre->user->avatar ? storage_url($centre->user->avatar) : null,
                ] : null,
                // camelCase so frontend AdminCentre.profileCentre works without transformation
                'profileCentre'     => $profileCentrePayload,
            ],
        ]);
    }

    /**
     * PATCH /admin/centres/{id}/profile-centre
     * Update ProfileCentre fields (price, category, capacity, contacts)
     * and bulk-update service availability/prices.
     */
    public function updateProfileCentre(Request $request, $id)
    {
        $centre = CampingCentre::with('profileCentre')->findOrFail($id);

        if (!$centre->profileCentre) {
            return response()->json(['message' => 'No ProfileCentre linked to this centre.'], 422);
        }

        $validated = $request->validate([
            'price_per_night' => 'nullable|numeric|min:0',
            'category'        => 'nullable|string|max:100',
            'capacite'        => 'nullable|integer|min:0',
            'contact_email'   => 'nullable|email|max:255',
            'contact_phone'   => 'nullable|string|max:50',
            'manager_name'    => 'nullable|string|max:255',
            'disponibilite'   => 'nullable|boolean',
            'services'        => 'nullable|array',
            'services.*.id'           => 'required|integer',
            'services.*.price'        => 'required|numeric|min:0',
            'services.*.is_available' => 'required|boolean',
            'equipment'       => 'nullable|array',
            'equipment.*.id'           => 'required|integer',
            'equipment.*.is_available' => 'required|boolean',
        ]);

        $pc = $centre->profileCentre;

        $pcFields = collect($validated)->only([
            'price_per_night', 'category', 'capacite',
            'contact_email', 'contact_phone', 'manager_name', 'disponibilite',
        ])->filter(fn($v) => !is_null($v))->toArray();

        if (!empty($pcFields)) {
            $pc->update($pcFields);
        }

        // Sync CampingCentre.status with disponibilite if it changed
        if (isset($validated['disponibilite'])) {
            $centre->update(['status' => (bool) $validated['disponibilite']]);
        }

        // Bulk-update service prices and availability
        if (!empty($validated['services'])) {
            foreach ($validated['services'] as $svc) {
                \App\Models\ProfileCenterService::where('profile_center_id', $pc->id)
                    ->where('id', $svc['id'])
                    ->update([
                        'price'        => $svc['price'],
                        'is_available' => $svc['is_available'],
                    ]);
            }
        }

        // Bulk-update equipment availability
        if (!empty($validated['equipment'])) {
            foreach ($validated['equipment'] as $eq) {
                \App\Models\ProfileCenterEquipment::where('profile_center_id', $eq['id'])
                    ->update(['is_available' => $eq['is_available']]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Centre profile updated.']);
    }

    /**
     * Lister tous les centres inscrits (user + profile + profile_centre)
     */
    public function registeredCentres()
    {
        $centres = CampingCentre::whereNotNull('user_id')
            ->with(['user.profile', 'profileCentre'])
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
    public function togglePartner($id)
    {
        $centre = CampingCentre::findOrFail($id);
        $centre->is_partner = ! $centre->is_partner;
        $centre->save();

        return response()->json([
            'message'    => $centre->is_partner ? 'Centre marqué comme partenaire' : 'Statut partenaire retiré',
            'is_partner' => $centre->is_partner,
        ]);
    }

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

        // Keep ProfileCentre.disponibilite in sync so the center appears
        // (or disappears) in the public list immediately after approval.
        if ($centre->profile_centre_id) {
            \App\Models\ProfileCentre::where('id', $centre->profile_centre_id)
                ->update(['disponibilite' => $centre->status]);
        }

        // Also activate the linked user so they pass the is_active=1 filter
        if ($centre->status && $centre->user_id) {
            \App\Models\User::where('id', $centre->user_id)
                ->update(['is_active' => 1]);
        }

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

        return response()->json($query->paginate(10));
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

        // Clear user_id from centre photos so re-linking a new user
        // stamps the correct owner and they become visible again.
        Photo::where('camping_centre_id', $centre->id)
            ->update(['user_id' => null]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur dissocié du centre.',
            'centre'  => $centre->fresh(['photos']),
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
            'photos.*'    => 'file|image|max:5120',
            'cover_index' => 'nullable|integer',
        ]);

        $centre     = CampingCentre::findOrFail($id);
        $coverIndex = (int) $request->input('cover_index', -1);
        $offset     = $centre->photos()->count();
        $hasImage   = !is_null($centre->image);

        foreach ($request->file('photos') as $index => $file) {
            $path    = $file->store('centre_photos/' . $centre->id, 'public');
            $isCover = ($index === $coverIndex);

            Photo::create([
                'path_to_img'       => $path,
                'camping_centre_id' => $centre->id,
                'is_cover'          => $isCover,
                'order'             => $offset + $index,
                // Stamp the linked user so the photo appears in the cover map immediately.
                'user_id'           => $centre->user_id,
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
                'url'        => storage_url($p->path_to_img),
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

        // 5. Stamp the linked user onto all centre photos so they are visible
        //    in both the public list (cover photo map) and the detail gallery.
        Photo::where('camping_centre_id', $centre->id)
            ->update(['user_id' => $user->id]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Centre lié au profil utilisateur avec succès.',
            'centre'  => $centre->fresh(['user', 'profileCentre', 'photos']),
        ]);
    }

    /**
     * GET /admin/centres/search-users?q=xxx
     * Recherche rapide d'utilisateurs pour l'assignation d'un centre.
     * Searches by first_name, last_name, email, or centre name.
     */
    public function searchUsers(Request $request)
    {
        $q = trim($request->query('q', ''));

        $users = User::with('role')
            ->leftJoin('camping_centres as cc', 'cc.user_id', '=', 'users.id')
            ->select('users.*', 'cc.nom as centre_nom')
            ->when($q !== '', fn($query) =>
                $query->where(function ($q2) use ($q) {
                    $q2->where('users.first_name', 'like', "%{$q}%")
                       ->orWhere('users.last_name',  'like', "%{$q}%")
                       ->orWhere('users.email',       'like', "%{$q}%")
                       ->orWhere('cc.nom',            'like', "%{$q}%");
                })
            )
            ->limit(10)
            ->get()
            ->unique('id')
            ->values()
            ->map(fn($u) => [
                'id'         => $u->id,
                'first_name' => $u->first_name,
                'last_name'  => $u->last_name,
                'email'      => $u->email,
                'avatar'     => $u->avatar,
                'role'       => $u->role?->name,
                'centre_nom' => $u->centre_nom,
            ]);

        return response()->json(['status' => 'success', 'data' => $users]);
    }

}