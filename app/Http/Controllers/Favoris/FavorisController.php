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
    /**
     * Add to favoris
     */
    public function addToFavorites(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id'   => 'required|exists:users,id',
                'target_id' => 'required|integer',
                'type'      => 'required|in:guide,centre,event,zone,annonce',
            ]);

            $exists = Favori::where('user_id', $validated['user_id'])
                ->where('target_id', $validated['target_id'])
                ->where('type', $validated['type'])
                ->exists();

            if ($exists) {
                Log::warning('Duplicate favorite attempt', $validated);
                return response()->json([
                    'success' => false,
                    'message' => 'Already in favorites.',
                ], 409); // Conflict
            }

            $favori = Favori::create($validated);

            Log::info('Favorite added successfully', [
                'favori_id' => $favori->id,
                'user_id'   => $favori->user_id,
                'target_id' => $favori->target_id,
                'type'      => $favori->type,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Added to favorites successfully.',
                'data'    => $favori,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed when adding favorite', [
                'errors' => $e->errors(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Unexpected error when adding favorite', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
            ], 500);
        }
    }



}
