<?php

namespace App\Http\Controllers\Boutique;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Boutiques;
use App\Http\Controllers\Controller;

class BoutiqueController extends Controller
{
    /**
     * Display all active boutiques (public listing).
     */
    public function index()
    {
        $boutiques = Boutiques::with('fournisseur')->get();

        return response()->json([
            'status'    => 'success',
            'boutiques' => $boutiques,
        ]);
    }

    /**
     * Placeholder — tells API consumers what fields are needed.
     */
    public function create()
    {
        return response()->json([
            'message' => 'Provide nom_boutique, optional description, and optional image to create a boutique.',
        ]);
    }

    /**
     * Show a specific boutique by its fournisseur_id.
     * Also returns the boutique's materiels for the shop page.
     */
    public function show(int $fournisseur_id)
    {
        $boutique = Boutiques::with(['fournisseur', 'materielles'])
            ->where('fournisseur_id', $fournisseur_id)
            ->first();

        if (!$boutique) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No boutique found for this fournisseur.',
            ], 404);
        }

        return response()->json([
            'status'   => 'success',
            'boutique' => $boutique,
        ]);
    }

    /**
     * Show a boutique by its own ID (for edit screens).
     */
    public function edit(int $boutique_id)
    {
        $boutique = Boutiques::find($boutique_id);

        if (!$boutique) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Boutique not found.',
            ], 404);
        }

        // Only the owning fournisseur can see the edit view
        if ($boutique->fournisseur_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'status'   => 'success',
            'boutique' => $boutique,
        ]);
    }

    /**
     * Create a new boutique for the authenticated fournisseur.
     * A fournisseur can only have one boutique.
     */
    public function add(Request $request)
    {
        $userId = Auth::id();

        // One boutique per fournisseur
        if (Boutiques::where('fournisseur_id', $userId)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Vous avez déjà une boutique.',
            ], 422);
        }

        $validated = $request->validate([
            'nom_boutique' => 'required|string|max:255',
            'description'  => 'nullable|string',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'nom_boutique.required' => 'Le nom de la boutique est obligatoire.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('boutiques', 'public');
        }

        $boutique = Boutiques::create([
            'fournisseur_id' => $userId,
            'nom_boutique'   => $validated['nom_boutique'],
            'description'    => $validated['description'] ?? null,
            'path_to_img'    => $imagePath,
            'status'         => false, // pending admin approval
        ]);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Boutique ajoutée avec succès.',
            'boutique' => $boutique,
        ], 201);
    }

    /**
     * Update the authenticated fournisseur's boutique.
     */
    public function update(Request $request)
    {
        $userId   = Auth::id();
        $boutique = Boutiques::where('fournisseur_id', $userId)->first();

        if (!$boutique) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune boutique trouvée pour mise à jour.',
            ], 404);
        }

        $validated = $request->validate([
            'nom_boutique' => 'sometimes|required|string|max:255',
            'description'  => 'nullable|string',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'nom_boutique.required' => 'Le nom de la boutique est requis.',
        ]);

        // Replace image if a new one was uploaded
        if ($request->hasFile('image')) {
            // Delete old image from storage
            if ($boutique->path_to_img) {
                Storage::disk('public')->delete($boutique->path_to_img);
            }
            $validated['path_to_img'] = $request->file('image')->store('boutiques', 'public');
        }

        $boutique->update($validated);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Boutique mise à jour avec succès.',
            'boutique' => $boutique,
        ]);
    }

    /**
     * Delete the authenticated fournisseur's boutique.
     */
    public function destroy()
    {
        $userId   = Auth::id();
        $boutique = Boutiques::where('fournisseur_id', $userId)->first();

        if (!$boutique) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune boutique à supprimer.',
            ], 404);
        }

        // Clean up stored image
        if ($boutique->path_to_img) {
            Storage::disk('public')->delete($boutique->path_to_img);
        }

        $boutique->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Boutique supprimée avec succès.',
        ]);
    }
}