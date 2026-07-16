<?php

namespace App\Http\Controllers\Boutique;

use App\Http\Controllers\Controller;
use App\Http\Requests\Boutique\StoreBoutiqueRequest;
use App\Http\Requests\Boutique\UpdateBoutiqueRequest;
use App\Models\Boutiques;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BoutiqueController extends Controller
{
    /**
     * Display all active boutiques (public listing).
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
    public function show($fournisseur_id)
    {
        $with = ['fournisseur.profile', 'materielles'];
        if (is_numeric($fournisseur_id)) {
            $boutique = Boutiques::with($with)->where('fournisseur_id', $fournisseur_id)->first();
        } else {
            $boutique = Boutiques::with($with)->where('slug', $fournisseur_id)->first();
            if (!$boutique && ($numId = static::decodeBase64Id($fournisseur_id))) {
                $boutique = Boutiques::with($with)->where('fournisseur_id', $numId)->first();
            }
        }

        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'No boutique found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'boutique' => $boutique,
        ]);
    }

    /**
     * Show a boutique by the supplier's UUID (public, for UUID-based navigation).
     */
    public function showByUuid(string $uuid)
    {
        $user = \App\Models\User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Supplier not found.'], 404);
        }

        return $this->show($user->id);
    }

    /**
     * Show a boutique by its own ID (for edit screens).
     */
    public function edit(int $boutique_id)
    {
        $boutique = Boutiques::find($boutique_id);

        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'Boutique not found.',
            ], 404);
        }

        // Only the owning fournisseur can see the edit view
        if ($boutique->fournisseur_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'status' => 'success',
            'boutique' => $boutique,
        ]);
    }

    /**
     * Create a new boutique for the authenticated fournisseur.
     * A fournisseur can only have one boutique.
     */
    public function add(StoreBoutiqueRequest $request)
    {
        $userId = Auth::id();

        // One boutique per fournisseur
        if (Boutiques::where('fournisseur_id', $userId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vous avez déjà une boutique.',
            ], 422);
        }

        $validated = $request->validated();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('boutiques', 'public');
        }

        $boutique = Boutiques::create([
            'fournisseur_id' => $userId,
            'nom_boutique' => $validated['nom_boutique'],
            'description' => $validated['description'] ?? null,
            'path_to_img' => $imagePath,
            'status' => false, // pending admin approval
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique ajoutée avec succès.',
            'boutique' => $boutique,
        ], 201);
    }

    /**
     * Update the authenticated fournisseur's boutique.
     */
    public function update(UpdateBoutiqueRequest $request)
    {
        $userId = Auth::id();
        $boutique = Boutiques::where('fournisseur_id', $userId)->first();

        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune boutique trouvée pour mise à jour.',
            ], 404);
        }

        $validated = $request->validated();

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
            'status' => 'success',
            'message' => 'Boutique mise à jour avec succès.',
            'boutique' => $boutique,
        ]);
    }

    /**
     * Delete the authenticated fournisseur's boutique.
     */
    public function destroy()
    {
        $userId = Auth::id();
        $boutique = Boutiques::where('fournisseur_id', $userId)->first();

        if (!$boutique) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune boutique à supprimer.',
            ], 404);
        }

        // Clean up stored image
        if ($boutique->path_to_img) {
            Storage::disk('public')->delete($boutique->path_to_img);
        }

        $boutique->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Boutique supprimée avec succès.',
        ]);
    }
}
