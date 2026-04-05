<?php

namespace App\Http\Controllers\Materielle;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Photo;
use App\Models\Materielles;
use App\Models\Boutiques;
use App\Http\Controllers\Controller;

class MaterielleController extends Controller
{
    /**
     * List all materiels belonging to a fournisseur.
     */
    public function index(int $fournisseur_id)
    {
        try {
            $materielles = Materielles::with(['category', 'photos'])
                ->where('fournisseur_id', $fournisseur_id)
                ->get();

            if ($materielles->isEmpty()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No materielles found for this fournisseur.',
                ], 404);
            }

            return response()->json(['status' => 'success', 'data' => $materielles]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to retrieve materielles.'], 500);
        }
    }
    /**
     * Get all categories for marketplace filter.
     */
    public function categories()
    {
        try {
            $categories = \App\Models\Materielles_categories::all();
            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }
    /**
     * Show a specific materiel with its photos, category and feedbacks.
     */
    public function show(int $materielle_id)
    {
        try {
            $materielle = Materielles::with(['photos', 'category', 'feedbacks', 'fournisseur.profile'])
                ->find($materielle_id);
    
            if (!$materielle) {
                return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
            }
    
            return response()->json(['status' => 'success', 'data' => $materielle]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Unable to retrieve materielle.'], 500);
        }
    }

    /**
     * Show a materiel for editing (only the owning fournisseur).
     */
    public function edit(int $materielle_id)
    {
        try {
            $materielle = Materielles::with('photos')->find($materielle_id);

            if (!$materielle) {
                return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
            }

            if ($materielle->fournisseur_id !== Auth::id()) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
            }

            return response()->json(['status' => 'success', 'data' => $materielle]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Unable to fetch materielle for editing.'], 500);
        }
    }

    /**
     * Compare two materiels side by side.
     */
    public function compare(int $id1, int $id2)
    {
        try {
            $m1 = Materielles::with(['photos', 'category'])->find($id1);
            $m2 = Materielles::with(['photos', 'category'])->find($id2);

            if (!$m1 || !$m2) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'One or both materielles not found.',
                ], 404);
            }

            return response()->json([
                'status'      => 'success',
                'materielle1' => $m1,
                'materielle2' => $m2,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Comparison failed.'], 500);
        }
    }

    /**
     * Create a new materiel listing.
     *
     * The fournisseur must have an active boutique.
     * Supports rent, sell, or both.
     * Supports pickup and/or delivery.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id'          => 'required|exists:materielles_categories,id',
            'nom'                  => 'required|string|max:255',
            'description'          => 'required|string',
            'is_rentable'          => 'boolean',
            'is_sellable'          => 'boolean',
            'tarif_nuit'           => 'nullable|numeric|min:0',
            'prix_vente'           => 'nullable|numeric|min:0',
            'quantite_total'       => 'required|integer|min:1',
            'quantite_dispo'       => 'required|integer|min:0',
            'livraison_disponible' => 'boolean',
            'frais_livraison'      => 'nullable|numeric|min:0',
            'image'                => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $isRentable = $request->boolean('is_rentable', true);
        $isSellable = $request->boolean('is_sellable', false);

        if (!$isRentable && !$isSellable) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le matériel doit être rentable, vendable, ou les deux.',
            ], 422);
        }

        if ($isRentable && empty($validated['tarif_nuit'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'tarif_nuit est requis pour un matériel en location.',
            ], 422);
        }

        if ($isSellable && empty($validated['prix_vente'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'prix_vente est requis pour un matériel en vente.',
            ], 422);
        }

        $fournisseurId = Auth::id();
        $user = Auth::user();
        $isSupplier = $user->role_id === 4;

        // Only suppliers need a boutique - campers can list equipment directly
        if ($isSupplier && !Boutiques::where('fournisseur_id', $fournisseurId)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Vous devez d\'abord créer une boutique.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $imagePath = $request->file('image')->store('materielles', 'public');

            $materielle = Materielles::create([
                'fournisseur_id'       => $fournisseurId,
                'category_id'          => $validated['category_id'],
                'nom'                  => $validated['nom'],
                'description'          => $validated['description'],
                'is_rentable'          => $isRentable,
                'is_sellable'          => $isSellable,
                'tarif_nuit'           => $isRentable ? $validated['tarif_nuit'] : null,
                'prix_vente'           => $isSellable ? $validated['prix_vente'] : null,
                'quantite_total'       => $validated['quantite_total'],
                'quantite_dispo'       => $validated['quantite_dispo'],
                'livraison_disponible' => $request->boolean('livraison_disponible', false),
                'frais_livraison'      => $request->boolean('livraison_disponible') ? ($validated['frais_livraison'] ?? 0) : null,
                'status'               => 'up',
            ]);

            Photo::create([
                'materielle_id' => $materielle->id,
                'path_to_img'   => $imagePath,
            ]);

            DB::commit();

            return response()->json([
                'status'     => 'success',
                'message'    => 'Matériel ajouté avec succès.',
                'materielle' => $materielle->load('photos'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to store materielle: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing materiel.
     * Only the owning fournisseur can update.
     */
    public function update(Request $request, int $id)
    {
        $materielle = Materielles::with('photos')->find($id);

        if (!$materielle) {
            return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
        }

        if ($materielle->fournisseur_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'category_id'          => 'sometimes|exists:materielles_categories,id',
            'nom'                  => 'sometimes|string|max:255',
            'description'          => 'sometimes|string',
            'is_rentable'          => 'boolean',
            'is_sellable'          => 'boolean',
            'tarif_nuit'           => 'nullable|numeric|min:0',
            'prix_vente'           => 'nullable|numeric|min:0',
            'quantite_total'       => 'sometimes|integer|min:1',
            'quantite_dispo'       => 'sometimes|integer|min:0',
            'livraison_disponible' => 'boolean',
            'frais_livraison'      => 'nullable|numeric|min:0',
            'images'               => 'nullable|array|max:8',
            'images.*'             => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'existing_photo_ids'   => 'nullable|array',
            'existing_photo_ids.*' => 'integer|exists:photos,id',
        ]);

        $isRentable = $request->has('is_rentable') ? $request->boolean('is_rentable') : $materielle->is_rentable;
        $isSellable = $request->has('is_sellable') ? $request->boolean('is_sellable') : $materielle->is_sellable;

        if (!$isRentable && !$isSellable) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le matériel doit être rentable, vendable, ou les deux.',
            ], 422);
        }

        DB::beginTransaction();
        try {

            if ($request->hasFile('images') || $request->has('existing_photo_ids')) {

                // 1. Resolve which existing photos to keep (in the submitted order)
                $keepIds = $request->input('existing_photo_ids', []);

                // 2. Delete photos NOT in the keep list
                foreach ($materielle->photos as $photo) {
                    if (!in_array($photo->id, $keepIds)) {
                        Storage::disk('public')->delete($photo->path_to_img);
                        $photo->delete();
                    }
                }

                // 3. Re-order / re-flag cover on kept photos
                foreach ($keepIds as $order => $photoId) {
                    Photo::where('id', $photoId)
                        ->where('materielle_id', $materielle->id)
                        ->update([
                            'order'    => $order,
                            'is_cover' => $order === 0,
                        ]);
                }

                // 4. Append new files after the kept ones (guarded — only when files exist)
                if ($request->hasFile('images')) {
                    $offset = count($keepIds);
                    foreach ($request->file('images') as $index => $file) {
                        Photo::create([
                            'materielle_id' => $materielle->id,
                            'path_to_img'   => $file->store('materielles', 'public'),
                            'is_cover'      => ($offset + $index) === 0,
                            'order'         => $offset + $index,
                        ]);
                    }
                }
            }

            $materielle->update([
                'category_id'          => $validated['category_id']   ?? $materielle->category_id,
                'nom'                  => $validated['nom']            ?? $materielle->nom,
                'description'          => $validated['description']    ?? $materielle->description,
                'is_rentable'          => $isRentable,
                'is_sellable'          => $isSellable,
                'tarif_nuit'           => $isRentable ? ($validated['tarif_nuit'] ?? $materielle->tarif_nuit) : null,
                'prix_vente'           => $isSellable ? ($validated['prix_vente'] ?? $materielle->prix_vente) : null,
                'quantite_total'       => $validated['quantite_total'] ?? $materielle->quantite_total,
                'quantite_dispo'       => $validated['quantite_dispo'] ?? $materielle->quantite_dispo,
                'livraison_disponible' => $request->has('livraison_disponible')
                    ? $request->boolean('livraison_disponible')
                    : $materielle->livraison_disponible,
                'frais_livraison'      => $validated['frais_livraison'] ?? $materielle->frais_livraison,
            ]);

            DB::commit();

            return response()->json([
                'status'     => 'success',
                'message'    => 'Matériel mis à jour avec succès.',
                'materielle' => $materielle->fresh('photos'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Delete a materiel and its associated photos.
     * Only the owning fournisseur can delete.
     */
    public function destroy(int $id)
    {
        $materielle = Materielles::with('photos')->find($id);

        if (!$materielle) {
            return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
        }

        if ($materielle->fournisseur_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        DB::beginTransaction();
        try {
            // Delete all photos from storage
            foreach ($materielle->photos as $photo) {
                Storage::disk('public')->delete($photo->path_to_img);
                $photo->delete();
            }

            $materielle->delete();
            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Matériel supprimé avec succès.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete materielle: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activate a materiel listing.
     */
    public function activate(int $id)
    {
        $materielle = Materielles::findOrFail($id);

        if (Auth::id() !== $materielle->fournisseur_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        if ($materielle->status === 'up') {
            return response()->json(['status' => 'info', 'message' => 'Le matériel est déjà actif.'], 400);
        }

        $materielle->update(['status' => 'up']);

        return response()->json([
            'status'     => 'success',
            'message'    => 'Matériel activé avec succès.',
            'materielle' => $materielle,
        ]);
    }

    /**
     * Deactivate a materiel listing.
     */
    public function deactivate(int $id)
    {
        $materielle = Materielles::findOrFail($id);

        if (Auth::id() !== $materielle->fournisseur_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        if ($materielle->status === 'down') {
            return response()->json(['status' => 'info', 'message' => 'Le matériel est déjà désactivé.'], 400);
        }

        $materielle->update(['status' => 'down']);

        return response()->json([
            'status'     => 'success',
            'message'    => 'Matériel désactivé avec succès.',
            'materielle' => $materielle,
        ]);
    }


    public function marketplace(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 12), 48);
 
        $query = Materielles::with(['photos', 'category', 'fournisseur:id,first_name,last_name,avatar'])
            ->where('status', 'up');
 
        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('nom',         'like', "%{$term}%")
                  ->orWhere('description','like', "%{$term}%");
            });
        }
 
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
 
        switch ($request->get('type')) {
            case 'location': $query->where('is_rentable', true);  break;
            case 'vente':    $query->where('is_sellable', true);  break;
            case 'both':
                $query->where('is_rentable', true)
                      ->where('is_sellable', true);
                break;
        }
 
        // Uses the "most relevant" price: tarif_nuit for rentals, prix_vente for sales.
        if ($request->filled('price_min') || $request->filled('price_max')) {
            $min = (float) $request->get('price_min', 0);
            $max = (float) $request->get('price_max', PHP_INT_MAX);
 
            $query->where(function ($q) use ($min, $max) {
                $q->whereBetween('tarif_nuit', [$min, $max])
                  ->orWhereBetween('prix_vente', [$min, $max]);
            });
        }
 
        if ($request->boolean('delivery')) {
            $query->where('livraison_disponible', true);
        }
 
        switch ($request->get('sort', 'newest')) {
            case 'price_asc':
                // Order by the available price (rental preferred, then sale)
                $query->orderByRaw('COALESCE(tarif_nuit, prix_vente) ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('COALESCE(tarif_nuit, prix_vente) DESC');
                break;
            case 'popular':
                // Count confirmed + retrieved reservations as a popularity proxy.
                // Swap for a rating column once reviews land on Materielles.
                $query->withCount([
                    'reservations as reservation_count' => fn($q) =>
                        $q->whereIn('status', ['confirmed','paid','retrieved','returned']),
                ])->orderByDesc('reservation_count');
                break;
            default: // 'newest'
                $query->latest();
        }
 
        $results = $query->paginate($perPage);
 
        $results->getCollection()->transform(function (Materielles $m) {
            $cover = $m->photos->firstWhere('is_cover', true)
                   ?? $m->photos->first();
 
            return [
                'id'                   => $m->id,
                'nom'                  => $m->nom,
                'description'          => $m->description,
                'is_rentable'          => $m->is_rentable,
                'is_sellable'          => $m->is_sellable,
                'tarif_nuit'           => $m->tarif_nuit,
                'prix_vente'           => $m->prix_vente,
                'quantite_dispo'       => $m->quantite_dispo,
                'livraison_disponible' => $m->livraison_disponible,
                'frais_livraison'      => $m->frais_livraison,
                'status'               => $m->status,
                'cover_image'          => $cover ? asset('storage/' . $cover->path_to_img) : null,
                'category'             => $m->category ? [
                    'id'  => $m->category->id,
                    'nom' => $m->category->nom,
                ] : null,
                'fournisseur'          => $m->fournisseur ? [
                    'id'         => $m->fournisseur->id,
                    'first_name' => $m->fournisseur->first_name,
                    'last_name'  => $m->fournisseur->last_name,
                    'avatar'     => $m->fournisseur->avatar,
                ] : null,
                'created_at'           => $m->created_at,
            ];
        });
 
        return response()->json([
            'status' => 'success',
            'data'   => $results,
        ]);
    }

    
}