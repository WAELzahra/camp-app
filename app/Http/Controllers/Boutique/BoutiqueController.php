<?php

namespace App\Http\Controllers\Boutique;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Boutiques;
use App\Http\Controllers\Controller;

class BoutiqueController extends Controller
{
    /**
     * Display all boutiques.
     */
    public function index()
    {
        $boutiques = Boutiques::with('fournisseur')->get();

        return response()->json([
            'status' => 'success',
            'boutiques' => $boutiques,
        ]);
    }

    /**
     * Return a message describing how to create a boutique.
     * (Useful for API/placeholder routes)
     */
    public function create()
    {
        return response()->json([
            'message' => 'Provide nom_boutique and optional description to create a boutique.'
        ]);
    }

    /**
     * Show a form-like response for editing a boutique.
     */
    public function edit(int $boutique_id)
    {
        $boutique = Boutiques::find($boutique_id);

        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'Boutique not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'boutique' => $boutique,
        ]);
    }

    /**
     * Show a specific boutique by ID.
     */
    public function show(int $fournisseur_id)
    {
        $boutique = Boutiques::where('fournisseur_id', $fournisseur_id)->first();
    
        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'No boutique found for this fournisseur.'
            ], 404);
        }
    
        return response()->json([
            'status' => 'success',
            'boutique' => $boutique,
        ]);
    }
    

    /**
     * Add a new boutique for the authenticated fournisseur.
     */
    public function add(Request $request)
    {
        $validated = $request->validate([
            'nom_boutique' => 'required|string',
            'description' => 'nullable|string',
        ], [
            'nom_boutique.required' => 'Le nom de la boutique est obligatoire.',
        ]);

        $userId = Auth::id();

        $boutique = Boutiques::create([
            'fournisseur_id' => $userId,
            'nom_boutique' => $request->nom_boutique,
            'description' => $request->description,
            'status' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique ajoutée avec succès.',
            'boutique' => $boutique,
        ], 201);
    }

    /**
     * Update the boutique for the authenticated fournisseur.
     */
    public function update(Request $request)
    {
        $request->validate([
            'nom_boutique' => 'required|string',
            'description' => 'nullable|string',
        ], [
            'nom_boutique.required' => 'Le nom de la boutique est requis.',
        ]);

        $userId = Auth::id();

        $updated = Boutiques::where('fournisseur_id', $userId)->first();

        if (!$updated) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune boutique trouvée pour mise à jour.'
            ], 404);
        }

        $updated->update([
            'nom_boutique' => $request->nom_boutique,
            'description' => $request->description,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique mise à jour avec succès.',
            'boutique' => $updated,
        ], 200);
    }

    /**
     * Delete the boutique owned by the authenticated fournisseur.
     */
    public function destroy()
    {
        $userId = Auth::id();

        $boutique = Boutiques::where('fournisseur_id', $userId)->first();

        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune boutique à supprimer.'
            ], 404);
        }

        $boutique->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique supprimée avec succès.'
        ], 200);
    }
}
