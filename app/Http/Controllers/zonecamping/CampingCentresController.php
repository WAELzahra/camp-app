<?php

namespace App\Http\Controllers\zonecamping;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CampingCentre;
use App\Models\Camping_zones;
use App\Models\Favoris;
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
     * Voir les détails d'un centre et ses zones associées
     */
    public function showCentre($id)
{
    // Charger le centre avec ses zones et les feedbacks de chaque zone
    $centre = CampingCentre::with(['zones.feedbacks.user', 'profileCentre', 'user.profile'])->findOrFail($id);

    // Préparer les données pour le front-end
    $centreData = [
        'id' => $centre->id,
        'nom' => $centre->nom ?? 'Centre non inscrit',
        'description' => $centre->description,
        'adresse' => $centre->adresse,
        'lat' => $centre->lat,
        'lng' => $centre->lng,
        'capacite' => $centre->profileCentre->capacite ?? null,
        'services_offerts' => $centre->profileCentre->services_offerts ?? null,
        'disponibilite' => $centre->profileCentre->disponibilite ?? null,
        'document_legal' => $centre->profileCentre->document_legal ?? null,
        'is_registered' => $centre->user_id ? true : false,
        'zones' => $centre->zones->map(function($zone){
            return [
                'id' => $zone->id,
                'nom' => $zone->nom,
                'lat' => $zone->lat,
                'lng' => $zone->lng,
                'description' => $zone->description,
                'status' => $zone->status,
                'feedbacks' => $zone->feedbacks->map(function($f){
                    return [
                        'id' => $f->id,
                        'user_name' => $f->user->name ?? 'Anonyme',
                        'comment' => $f->comment,
                        'rating' => $f->rating,
                        'created_at' => $f->created_at,
                    ];
                }),
            ];
        }),
    ];

    return response()->json([
        'status' => 'success',
        'data' => $centreData
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