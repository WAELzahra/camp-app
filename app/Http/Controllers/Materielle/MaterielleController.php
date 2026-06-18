<?php

namespace App\Http\Controllers\Materielle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Materielle\QuoteMaterielleRequest;
use App\Http\Requests\Materielle\StoreMaterielleRequest;
use App\Http\Requests\Materielle\UpdateMaterielleRequest;
use App\Models\Boutiques;
use App\Models\Materielles;
use App\Models\MaterielleSeasonalRate;
use App\Models\Photo;
use App\Services\MaterielPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaterielleController extends Controller
{
    /**
     * Validation rules for the seasonal_rates payload (shared by store/update).
     */
    private const SEASONAL_RATE_RULES = [
        'seasonal_rates' => 'nullable|array|max:12',
        'seasonal_rates.*.name' => 'required|string|max:100',
        'seasonal_rates.*.start_month' => 'required|integer|between:1,12',
        'seasonal_rates.*.start_day' => 'required|integer|between:1,31',
        'seasonal_rates.*.end_month' => 'required|integer|between:1,12',
        'seasonal_rates.*.end_day' => 'required|integer|between:1,31',
        'seasonal_rates.*.price_weekday' => 'required|numeric|min:0',
        'seasonal_rates.*.price_weekend' => 'nullable|numeric|min:0',
    ];

    /**
     * Replace a materiel's seasonal rates with the submitted set.
     * Accepts a JSON string (multipart forms) or an array.
     */
    private function syncSeasonalRates(Materielles $materielle, $rates): void
    {
        if (is_string($rates)) {
            $rates = json_decode($rates, true);
        }
        if (!is_array($rates)) {
            return;
        }

        $materielle->seasonalRates()->delete();
        foreach ($rates as $rate) {
            if (!isset($rate['name'], $rate['start_month'], $rate['start_day'],
                $rate['end_month'], $rate['end_day'], $rate['price_weekday'])) {
                continue;
            }
            MaterielleSeasonalRate::create([
                'materielle_id' => $materielle->id,
                'name' => substr((string) $rate['name'], 0, 100),
                'start_month' => (int) $rate['start_month'],
                'start_day' => (int) $rate['start_day'],
                'end_month' => (int) $rate['end_month'],
                'end_day' => (int) $rate['end_day'],
                'price_weekday' => (float) $rate['price_weekday'],
                'price_weekend' => isset($rate['price_weekend']) && $rate['price_weekend'] !== ''
                    ? (float) $rate['price_weekend'] : null,
            ]);
        }
    }

    /**
     * GET /materielles/{id}/quote — server-side rental price quote.
     * Query: date_debut, date_fin (night unit) | date_debut + hours (hour unit), quantite
     */
    public function quote(QuoteMaterielleRequest $request, int $id)
    {
        $materielle = Materielles::with('seasonalRates')->find($id);
        if (!$materielle) {
            return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
        }

        $validated = $request->validated();

        try {
            $quote = MaterielPricingService::quoteRental(
                $materielle,
                $validated['date_debut'] ?? null,
                $validated['date_fin'] ?? null,
                (int) ($validated['quantite'] ?? 1),
                isset($validated['hours']) ? (int) $validated['hours'] : null
            );

            return response()->json(['status' => 'success', 'data' => $quote]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

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
                    'status' => 'error',
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
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories',
            ], 500);
        }
    }

    /**
     * Show a specific materiel with its photos, category and feedbacks.
     */
    public function show(int $materielle_id)
    {
        try {
            $materielle = Materielles::with(['photos', 'category', 'feedbacks', 'fournisseur.profile', 'seasonalRates'])
                ->find($materielle_id);

            if (!$materielle) {
                return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
            }

            // Expose uuid and role_id on the fournisseur so the frontend can
            // determine provider type (supplier vs camper) and build profile links.
            if ($materielle->fournisseur) {
                $materielle->fournisseur->makeVisible(['id', 'role_id']);
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
            $materielle = Materielles::with(['photos', 'seasonalRates'])->find($materielle_id);

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
                    'status' => 'error',
                    'message' => 'One or both materielles not found.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
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
    public function store(StoreMaterielleRequest $request)
    {
        // seasonal_rates arrives as a JSON string in multipart forms — decode before validating
        if (is_string($request->input('seasonal_rates'))) {
            $request->merge(['seasonal_rates' => json_decode($request->input('seasonal_rates'), true)]);
        }

        $validated = $request->validated();

        $isRentable = $request->boolean('is_rentable', true);
        $isSellable = $request->boolean('is_sellable', false);
        $rentalUnit = $validated['rental_unit'] ?? 'night';

        if (!$isRentable && !$isSellable) {
            return response()->json([
                'status' => 'error',
                'message' => 'Le matériel doit être rentable, vendable, ou les deux.',
            ], 422);
        }

        if ($isRentable && $rentalUnit === 'night' && empty($validated['tarif_nuit'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'tarif_nuit est requis pour un matériel en location.',
            ], 422);
        }

        if ($isRentable && $rentalUnit === 'hour' && empty($validated['tarif_heure'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'tarif_heure est requis pour une location à l\'heure.',
            ], 422);
        }

        if ($isSellable && empty($validated['prix_vente'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'prix_vente est requis pour un matériel en vente.',
            ], 422);
        }

        $fournisseurId = Auth::id();
        $user = Auth::user();
        $isSupplier = $user->role_id === 4;

        // Only suppliers need a boutique - campers can list equipment directly
        if ($isSupplier && !Boutiques::where('fournisseur_id', $fournisseurId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vous devez d\'abord créer une boutique.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $imagePath = $request->file('image')->store('materielles', 'public');

            $materielle = Materielles::create([
                'fournisseur_id' => $fournisseurId,
                'category_id' => $validated['category_id'],
                'nom' => $validated['nom'],
                'brand' => $validated['brand'] ?? null,
                'description' => $validated['description'],
                'trip_type_tags' => $validated['trip_type_tags'] ?? null,
                'weight_kg' => $validated['weight_kg'] ?? null,
                'condition' => $validated['condition'] ?? 'new',
                'is_rentable' => $isRentable,
                'is_sellable' => $isSellable,
                'rental_unit' => $rentalUnit,
                'tarif_nuit' => $isRentable && $rentalUnit === 'night' ? $validated['tarif_nuit'] : null,
                'tarif_heure' => $isRentable && $rentalUnit === 'hour' ? $validated['tarif_heure'] : null,
                'prix_vente' => $isSellable ? $validated['prix_vente'] : null,
                'quantite_total' => $validated['quantite_total'],
                'quantite_dispo' => $validated['quantite_dispo'],
                'livraison_disponible' => $request->boolean('livraison_disponible', false),
                'frais_livraison' => $request->boolean('livraison_disponible') ? ($validated['frais_livraison'] ?? 0) : null,
                'status' => 'up',
            ]);

            Photo::create([
                'materielle_id' => $materielle->id,
                'path_to_img' => $imagePath,
            ]);

            if ($isRentable && !empty($validated['seasonal_rates'])) {
                $this->syncSeasonalRates($materielle, $validated['seasonal_rates']);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Matériel ajouté avec succès.',
                'materielle' => $materielle->load('photos'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to save the equipment. Please try again.',
            ], 500);
        }
    }

    /**
     * Update an existing materiel.
     * Only the owning fournisseur can update.
     */
    public function update(UpdateMaterielleRequest $request, int $id)
    {
        $materielle = Materielles::with('photos')->find($id);

        if (!$materielle) {
            return response()->json(['status' => 'error', 'message' => 'Materielle not found.'], 404);
        }

        if ($materielle->fournisseur_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        // seasonal_rates arrives as a JSON string in multipart forms — decode before validating
        if (is_string($request->input('seasonal_rates'))) {
            $request->merge(['seasonal_rates' => json_decode($request->input('seasonal_rates'), true)]);
        }

        $validated = $request->validated();

        $isRentable = $request->has('is_rentable') ? $request->boolean('is_rentable') : $materielle->is_rentable;
        $isSellable = $request->has('is_sellable') ? $request->boolean('is_sellable') : $materielle->is_sellable;

        if (!$isRentable && !$isSellable) {
            return response()->json([
                'status' => 'error',
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
                            'order' => $order,
                            'is_cover' => $order === 0,
                        ]);
                }

                // 4. Append new files after the kept ones (guarded — only when files exist)
                if ($request->hasFile('images')) {
                    $offset = count($keepIds);
                    foreach ($request->file('images') as $index => $file) {
                        Photo::create([
                            'materielle_id' => $materielle->id,
                            'path_to_img' => $file->store('materielles', 'public'),
                            'is_cover' => ($offset + $index) === 0,
                            'order' => $offset + $index,
                        ]);
                    }
                }
            }

            $materielle->update([
                'category_id' => $validated['category_id'] ?? $materielle->category_id,
                'nom' => $validated['nom'] ?? $materielle->nom,
                'brand' => array_key_exists('brand', $validated) ? $validated['brand'] : $materielle->brand,
                'description' => $validated['description'] ?? $materielle->description,
                'trip_type_tags' => array_key_exists('trip_type_tags', $validated) ? $validated['trip_type_tags'] : $materielle->trip_type_tags,
                'weight_kg' => array_key_exists('weight_kg', $validated) ? $validated['weight_kg'] : $materielle->weight_kg,
                'condition' => array_key_exists('condition', $validated) ? $validated['condition'] : $materielle->condition,
                'is_rentable' => $isRentable,
                'is_sellable' => $isSellable,
                'rental_unit' => $validated['rental_unit'] ?? $materielle->rental_unit ?? 'night',
                'tarif_nuit' => $isRentable ? ($validated['tarif_nuit'] ?? $materielle->tarif_nuit) : null,
                'tarif_heure' => $isRentable ? ($validated['tarif_heure'] ?? $materielle->tarif_heure) : null,
                'prix_vente' => $isSellable ? ($validated['prix_vente'] ?? $materielle->prix_vente) : null,
                'quantite_total' => $validated['quantite_total'] ?? $materielle->quantite_total,
                'quantite_dispo' => $validated['quantite_dispo'] ?? $materielle->quantite_dispo,
                'livraison_disponible' => $request->has('livraison_disponible')
                    ? $request->boolean('livraison_disponible')
                    : $materielle->livraison_disponible,
                'frais_livraison' => $validated['frais_livraison'] ?? $materielle->frais_livraison,
            ]);

            // Replace seasonal rates whenever the key is present in the request
            if ($request->has('seasonal_rates')) {
                $this->syncSeasonalRates($materielle, $request->input('seasonal_rates'));
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Matériel mis à jour avec succès.',
                'materielle' => $materielle->fresh(['photos', 'seasonalRates']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to update the equipment. Please try again.',
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
                'status' => 'success',
                'message' => 'Matériel supprimé avec succès.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to delete the equipment. Please try again.',
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
            'status' => 'success',
            'message' => 'Matériel activé avec succès.',
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
            'status' => 'success',
            'message' => 'Matériel désactivé avec succès.',
            'materielle' => $materielle,
        ]);
    }

    public function marketplace(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 12), 48);

        $query = Materielles::with(['photos', 'category', 'fournisseur:id,first_name,last_name,avatar', 'seasonalRates'])
            ->where('status', 'up');

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('nom', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        switch ($request->get('type')) {
            case 'location': $query->where('is_rentable', true);
                break;
            case 'vente':    $query->where('is_sellable', true);
                break;
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
                    'reservations as reservation_count' => fn ($q) => $q->whereIn('status', ['confirmed', 'paid', 'retrieved', 'returned']),
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
                'id' => $m->id,
                'nom' => $m->nom,
                'description' => $m->description,
                'is_rentable' => $m->is_rentable,
                'is_sellable' => $m->is_sellable,
                'rental_unit' => $m->rental_unit ?? 'night',
                'tarif_nuit' => $m->tarif_nuit,
                'tarif_heure' => $m->tarif_heure,
                'prix_vente' => $m->prix_vente,
                'quantite_dispo' => $m->quantite_dispo,
                'livraison_disponible' => $m->livraison_disponible,
                'frais_livraison' => $m->frais_livraison,
                'status' => $m->status,
                'cover_image' => $cover ? storage_url($cover->path_to_img) : null,
                'category' => $m->category ? [
                    'id' => $m->category->id,
                    'nom' => $m->category->nom,
                ] : null,
                'fournisseur' => $m->fournisseur ? [
                    'id' => $m->fournisseur->id,
                    'first_name' => $m->fournisseur->first_name,
                    'last_name' => $m->fournisseur->last_name,
                    'avatar' => $m->fournisseur->avatar,
                ] : null,
                'seasonal_rates' => $m->seasonalRates->map(fn ($r) => [
                    'name' => $r->name,
                    'start_month' => $r->start_month,
                    'start_day' => $r->start_day,
                    'end_month' => $r->end_month,
                    'end_day' => $r->end_day,
                    'price_weekday' => $r->price_weekday,
                    'price_weekend' => $r->price_weekend,
                ])->values(),
                'created_at' => $m->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ]);
    }
}
