<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CampingCentre;
use App\Models\Camping_zones;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CampingCentreController extends Controller
{
   /**
     * Lister tous les centres
     * Inclut les centres inscrits (user + profile + profile_centre)
     */
    public function index()
    {
        $centres = CampingCentre::with([
            'zones',
            'user.profile',         // pour centres inscrits
            'profileCentre'         // pour infos supplémentaires
        ])->get();

        return response()->json([
            'status' => 'success',
            'centres' => $centres
        ]);
    }

    /**
     * Ajouter un centre (admin peut créer un centre "manuel")
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string',
            'adresse' => 'required|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'type' => 'nullable|string',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id', // lien avec user inscrit
        ]);

        $centre = CampingCentre::create([
            'nom' => $request->nom,
            'adresse' => $request->adresse,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'type' => $request->type,
            'description' => $request->description,
            'image' => $request->image,
            'status' => 'active',
            'added_by' => Auth::id(),
            'user_id' => $request->user_id ?? null, // lien avec user si centre inscrit
        ]);

        return response()->json([
            'message' => 'Centre créé avec succès',
            'centre' => $centre
        ], 201);
    }

    // Mettre à jour un centre
    public function update(Request $request, $id)
{
    // Récupérer le centre
    $centre = CampingCentre::findOrFail($id);

    // Validation des données reçues
    $data = $request->validate([
        'nom' => 'sometimes|string|max:255',
        'adresse' => 'sometimes|string|max:255',
        'lat' => 'sometimes|numeric',
        'lng' => 'sometimes|numeric',
        'type' => 'sometimes|string',
        'image' => 'nullable|image|max:2048',
        'description' => 'nullable|string',
        'status' => 'sometimes|boolean',
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
            'zones'                 // zones associées
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'centre' => $centre
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

}