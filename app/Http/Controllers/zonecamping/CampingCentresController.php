<?php

namespace App\Http\Controllers\zonecamping;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CampingCentre;
use App\Models\Camping_zones;
use App\Models\Favoris;
use App\Models\ProfileCentre;
use App\Models\Photo;
use Illuminate\Support\Facades\Auth;
class CampingCentresController extends Controller
{
    /**
     * Retourne tous les centres pour affichage sur carte
     */
    public function getCentresMap()
    {
        $centres = CampingCentre::select('id', 'nom', 'adresse', 'lat', 'lng', 'type', 'image')->get();

        return response()->json([
            'status' => 'success',
            'data' => $centres
        ]);
    }

    /**
     * Returns all registered ProfileCentre records with coordinates + photos
     * for the interactive map overlay.
     */
    public function registeredCentresMap()
    {
        $centres = ProfileCentre::with(['profile.user'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('disponibilite', true)
            ->get();

        $data = $centres->map(function ($centre) {
            $userId = $centre->profile?->user_id;
            $photos = [];
            if ($userId) {
                $photos = Photo::where('user_id', $userId)
                    ->limit(5)
                    ->pluck('path_to_img')
                    ->map(fn($p) => asset('storage/' . $p))
                    ->values()
                    ->toArray();
            }

            return [
                'id'          => $centre->id,
                'name'        => $centre->name,
                'description' => $centre->profile?->bio ?? '',
                'latitude'    => (float) $centre->latitude,
                'longitude'   => (float) $centre->longitude,
                'photos'      => $photos,
                'price'       => $centre->price_per_night,
                'category'    => $centre->category,
                'capacity'    => $centre->capacite,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Public detail for a single CampingCentre (both partner and non-partner).
     * Returns a frontend-compatible shape matching the detail-centre.tsx normalizer.
     */
    public function showCentre($id)
    {
        $centre = CampingCentre::with(['zones', 'profileCentre', 'user.profile'])->findOrFail($id);

        $pc      = $centre->profileCentre;
        $isPartner = ! is_null($centre->user_id) || ! is_null($centre->profile_centre_id);

        $coverImage = null;
        if ($centre->image) {
            $coverImage = filter_var($centre->image, FILTER_VALIDATE_URL)
                ? $centre->image
                : url('storage/' . $centre->image);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'               => $centre->id,
                'name'             => $centre->nom,
                'capacite'         => $pc?->capacite ?? 0,
                'price_per_night'  => $pc ? (float) $pc->price_per_night : 0,
                'category'         => $pc?->category ?? 'Camping',
                'disponibilite'    => $pc ? (bool) $pc->disponibilite : true,
                'latitude'         => $centre->lat  ? (string) $centre->lat  : null,
                'longitude'        => $centre->lng  ? (string) $centre->lng  : null,
                'contact_email'    => $pc?->contact_email,
                'contact_phone'    => $pc?->contact_phone,
                'manager_name'     => $pc?->manager_name,
                'average_rating'   => null,
                'review_count'     => 0,
                'is_partner'       => $isPartner,
                '_source'          => 'camping',
                'profile' => [
                    'bio'         => $centre->description,
                    'city'        => $centre->user?->profile?->city ?? null,
                    'address'     => $centre->adresse,
                    'cover_image' => $coverImage,
                    'user'        => $centre->user ? [
                        'id'         => $centre->user->id,
                        'first_name' => $centre->user->first_name,
                        'last_name'  => $centre->user->last_name,
                    ] : null,
                ],
                'available_services'  => [],
                'available_equipment' => [],
                'zones' => $centre->zones->map(fn($z) => [
                    'id'          => $z->id,
                    'nom'         => $z->nom,
                    'lat'         => $z->lat,
                    'lng'         => $z->lng,
                    'description' => $z->description,
                    'status'      => $z->status,
                ])->values(),
            ],
        ]);
    }


    /**
     * Recherche des centres par nom ou type
     */
    public function searchCentres(Request $request)
    {
        $query = CampingCentre::query();

        if ($request->filled('nom')) {
            $query->where('nom', 'LIKE', "%{$request->nom}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    /**
     * Lister toutes les zones d'un centre
     */
    public function listZones($centreId)
    {
        $centre = CampingCentre::with(['zones' => function($q) {
            $q->where('status', true); // seulement les zones validées
        }])->findOrFail($centreId);

        return response()->json([
            'status' => 'success',
            'centre' => $centre,
            'zones' => $centre->zones
        ]);
    }

    /**
     * Permet à un utilisateur de suggérer un nouveau centre
     */
   public function suggestCentre(Request $request)
{
    $data = $request->validate([
        'nom' => 'required|string',
        'adresse' => 'required|string',
        'lat' => 'required|numeric',
        'lng' => 'required|numeric',
        'type' => 'nullable|string',
        'description' => 'nullable|string',
        'image' => 'nullable|string',
    ]);

    $data['status'] = 0; // privé par défaut
    $data['validation_status'] = 'pending'; // en attente de validation admin
    $data['added_by'] = Auth::id();
    $data['source'] = 'user';

    $centre = CampingCentre::create($data);

    return response()->json([
        'message' => 'Centre suggéré avec succès, en attente de validation',
        'centre' => $centre
    ], 201);
}


    /**
     * Lister les centres favoris d'un utilisateur
     */
    public function listFavoris()
    {
        $user = Auth::user();
        $favoris = $user->favoris()->where('type', 'centre')->with('target')->get()->map(fn($f) => $f->target);

        return response()->json([
            'status' => 'success',
            'favoris' => $favoris
        ]);
    }

    /**
     * Ajouter / retirer un centre aux favoris
     */
    public function toggleFavoris($centreId)
    {
        $user = Auth::user();

        // Récupération du centre
        $centre = \App\Models\CampingCentre::findOrFail($centreId);

        // Si le centre est non inscrit, target_id = null
        $targetId = $centre->isRegistered() ? $centreId : null;

        // Vérifie si le favori existe déjà
        $favoriQuery = $user->favoris()->where('type', 'centre');
        if ($targetId) {
            $favoriQuery->where('target_id', $targetId);
        }
        $favori = $favoriQuery->first();

        if ($favori) {
            $favori->delete();
            return response()->json(['message' => 'Centre retiré des favoris']);
        }

        // Ajout du favori
        $user->favoris()->create([
            'target_id' => $targetId,
            'type' => 'centre',
        ]);

        return response()->json(['message' => 'Centre ajouté aux favoris']);
    }




    /**
     * Get a centre by its owner user_id — used by announcements "Visit Center" CTA.
     */
    public function getByUser($userId)
    {
        $centre = CampingCentre::where('user_id', $userId)->first();
        if (!$centre) {
            return response()->json(['message' => 'Centre not found'], 404);
        }
        return response()->json(['id' => $centre->id]);
    }

        /**
     * Générer un lien de partage (Facebook, WhatsApp, etc.)
     */
public function shareCentre($id)
{
    $centre = CampingCentre::find($id);

    if (!$centre) {
        return response()->json([
            'message' => 'Centre non trouvé'
        ], 404);
    }

    $url = url('/centres/' . $centre->id);

    return response()->json([
        'message' => 'Lien de partage généré',
        'url' => $url,
        'share_links' => [
            'whatsapp'  => "https://wa.me/?text=" . urlencode("Découvrez ce lieu : " . $centre->nom . " " . $url),
            'facebook'  => "https://www.facebook.com/sharer/sharer.php?u=" . $url,
            'instagram' => $url // Instagram ne supporte pas de partage direct
        ]
    ]);
}





}