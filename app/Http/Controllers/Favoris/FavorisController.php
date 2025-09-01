<?php

namespace App\Http\Controllers\Favoris;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Camping_Zones;
use App\Models\CampingCentre;
use App\Models\Events;
use App\Models\Favoris;

class FavorisController extends Controller
{
    // Ajouter ou retirer une zone des favoris
    public function toggleZone(Request $request, $zoneId)
    {
        $user = auth()->user();

        // Récupérer la zone
        $zone = Camping_Zones::findOrFail($zoneId);

        // Vérifier si elle est déjà en favoris
        $favori = Favoris::where('user_id', $user->id)
            ->where('target_id', $zone->id)
            ->where('type', 'zone') // correspond à l'ENUM
            ->first();

        if ($favori) {
            // Supprimer correctement sans id
            Favoris::where('user_id', $user->id)
                ->where('target_id', $zone->id)
                ->where('type', 'zone')
                ->delete();

            return response()->json(['message' => 'Zone retirée des favoris']);
        }

        // Ajouter aux favoris
        Favoris::create([
            'user_id' => $user->id,
            'target_id' => $zone->id,
            'type' => 'zone', // <-- corrigé pour correspondre à l'ENUM
        ]);

        return response()->json(['message' => 'Zone ajoutée aux favoris']);
    }

    // Lister tous les favoris de l'utilisateur avec leurs détails
    public function listFavoris()
    {
        $user = auth()->user();
        $favoris = Favoris::where('user_id', $user->id)->get();

        $favorisDetails = $favoris->map(function ($f) {
            return $f->getTargetModel(); // utiliser la fonction normale
        })->filter(); // supprime les null si la cible n'existe plus

        return response()->json($favorisDetails->values());
    }



}
