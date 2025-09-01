<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Camping_zones;
use App\Models\CampingCentre;
use Illuminate\Support\Facades\Storage;

class CampingZoneController extends Controller
{

    /**
     * Création d'une zone (admin).
     * L'admin peut :
     * - Créer directement une zone validée
     * - Créer un centre associé si nécessaire
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string',
            'type_activitee' => 'required|string',
            'description' => 'nullable|string',
            'adresse' => 'nullable|string',
            'danger_level' => 'in:low,moderate,high,extreme',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'image' => 'nullable|string',
            'centre_id' => 'nullable|exists:camping_centres,id',
        ]);

        // Création centre si non existant
        if (empty($data['centre_id'])) {
            $centre = CampingCentre::create([
                'nom' => $data['nom'].' Centre',
                'adresse' => $data['adresse'] ?? null,
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'type' => 'hors_centre',
                'description' => 'Créé automatiquement via zone',
            ]);
            $data['centre_id'] = $centre->id;
        }

        // Validation directe par l'admin
        $data['status'] = true;
        $data['source'] = 'interne';
        $data['added_by'] = auth()->id();
        $data['created_by_role'] = auth()->user()->role;

        $zone = Camping_zones::create($data);

        return response()->json($zone, 201);
    }


      // Mise à jour zone de camping
   public function update(Request $request, $id)
{
    $zone = Camping_zones::findOrFail($id);
    $user = auth()->user();

    // Vérification via la relation de rôle
    if($user->role->name != 'admin'){ // Supposant que le champ s'appelle 'name'
        if($zone->added_by != $user->id || $zone->status == true){
            return response()->json(['message'=>'Modification non autorisée'],403);
        }
    }

        $data = $request->validate([
            'nom' => 'sometimes|string',
            'type_activitee' => 'sometimes|string',
            'description' => 'nullable|string',
            'adresse' => 'nullable|string',
            'danger_level' => 'in:low,moderate,high,extreme',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'image' => 'nullable|image|max:2048',
            'centre_id' => 'nullable|exists:camping_centres,id',
        ]);

        if ($request->hasFile('image')) {
            if ($zone->image) {
                Storage::disk('public')->delete($zone->image);
            }
            $path = $request->file('image')->store('zones', 'public');
            $data['image'] = $path;
        }

        // Si modifié par non-admin -> remettre en attente validation
        if(auth()->user()->role != 'admin'){
            $data['status'] = false;
        }

        $zone->update($data);

        return response()->json($zone);
    }

    // Suppression zone
    public function destroy($id)
{
    try {
        $zone = Camping_zones::findOrFail($id);
        $user = auth()->user();

        // Vérifier que l'utilisateur est authentifié
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        // Charger la relation role
        $user->load('role');

        // Vérification du rôle admin
        $isAdmin = strtolower($user->role->name) === 'admin'; // Adaptez au champ réel

        if(!$isAdmin) {
            // Vérifier que l'utilisateur est le propriétaire de la zone
            if($zone->added_by != $user->id) {
                return response()->json(['message'=>'Vous ne pouvez pas modifier cette zone'], 403);
            }
            
            // Désactiver la zone pour les non-admins
            $zone->status = false;
            $zone->save();
            
            return response()->json([
                'message' => 'Zone désactivée avec succès',
                'data' => $zone
            ]);
        }

        // Suppression définitive par admin
        if ($zone->image) {
            Storage::disk('public')->delete($zone->image);
        }
        
        $zone->delete();

        return response()->json([
            'message' => 'Zone supprimée définitivement avec succès'
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['message' => 'Zone non trouvée'], 404);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Erreur lors de la suppression'], 500);
    }
}

    /**
     * Valider une zone proposée ou créée.
     * Accessible uniquement par un admin.
     */
    public function validateZone($id)
{
    $user = auth()->user();
    $user->load('role');
    
    // Vérification du rôle admin
    if (!$user->role || strtolower($user->role->name) !== 'admin') {
        return response()->json(['message' => 'Accès refusé'], 403);
    }

    $zone = Camping_Zones::findOrFail($id);

    // Vérification si la zone est déjà publique
    if ($zone->is_public == 1) {
        return response()->json(['message' => 'Cette zone est déjà validée et publique'], 400);
    }

    // Simple validation : passer is_public à 1
    $zone->is_public = 1;
    $zone->save();

    return response()->json([
        'message' => 'Zone validée et rendue publique avec succès',
        'zone' => $zone
    ]);
    }

    /**
     * Activer ou désactiver une zone.
     * Seul un admin peut effectuer cette action.
     */
    public function toggleZoneStatus(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'message' => 'Accès refusé. Seul un administrateur peut effectuer cette action.'
            ], 403);
        }

        $request->validate([
            'status' => 'required|boolean'
        ]);

        $zone = Camping_zones::findOrFail($id);
        $zone->status = $request->status;
        $zone->save();

        return response()->json([
            'message' => $zone->status ? 'Zone activée avec succès' : 'Zone désactivée avec succès',
            'zone' => $zone
        ]);
    }

    /**
     * Fusionner deux zones proches.
     * Les informations manquantes de la zone secondaire sont complétées dans la principale.
     */
    public function merge(Request $request)
    {
        $data = $request->validate([
            'primary_zone_id' => 'required|exists:camping_zones,id',
            'secondary_zone_id' => 'required|exists:camping_zones,id'
        ]);

        $primary = Camping_zones::findOrFail($data['primary_zone_id']);
        $secondary = Camping_zones::findOrFail($data['secondary_zone_id']);

        foreach ($secondary->toArray() as $key => $value) {
            if (empty($primary->$key) && !empty($value)) {
                $primary->$key = $value;
            }
        }

        $primary->save();
        $secondary->delete();

        return response()->json(['message' => 'Zones fusionnées', 'zone' => $primary]);
    }

    /**
     * Afficher les statistiques générales des zones.
     * Total, publiques, privées, danger élevé, avec ou sans centre.
     */
    public function stats()
    {
        return response()->json([
            'total_zones' => Camping_zones::count(),
            'zones_publiques' => Camping_zones::where('is_public', true)->count(),
            'zones_privees' => Camping_zones::where('is_public', false)->count(),
            'zones_danger_haut' => Camping_zones::where('danger_level', 'high')->count(),
            'zones_par_centre' => Camping_zones::whereNotNull('centre_id')->count(),
            'zones_sans_centre' => Camping_zones::whereNull('centre_id')->count(),
        ]);
    }

    /**
     * Associer plusieurs zones à un centre d'un coup.
     * Pratique pour les admins qui veulent gérer rapidement les zones.
     */
    public function bulkAssignToCentre(Request $request)
    {
        $validated = $request->validate([
            'zone_ids' => 'required|array',
            'centre_id' => 'nullable|exists:users,id',
            'centre_name' => 'nullable|string|max:255',
            'centre_contact' => 'nullable|string|max:255',
        ]);

        foreach ($validated['zone_ids'] as $zoneId) {
            $zone = Camping_zones::findOrFail($zoneId);

            if (!empty($validated['centre_id'])) {
                $zone->centre_id = $validated['centre_id'];
            } else {
                $zone->centre_name = $validated['centre_name'] ?? 'Centre non inscrit';
                $zone->centre_contact = $validated['centre_contact'] ?? null;
            }

            $zone->save();
        }

        return response()->json(['message' => 'Zones associées avec succès.']);
    }

  



    }
