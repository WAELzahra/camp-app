<?php

namespace App\Http\Requests\Materielle;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaterielleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'category_id' => 'sometimes|exists:materielles_categories,id',
            'nom' => 'sometimes|string|max:255',
            'brand' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|string',
            'trip_type_tags' => 'sometimes|nullable|array',
            'trip_type_tags.*' => 'string|max:100',
            'weight_kg' => 'sometimes|nullable|numeric|min:0',
            'condition' => 'sometimes|nullable|in:new,like_new,good,fair',
            'is_rentable' => 'boolean',
            'is_sellable' => 'boolean',
            'rental_unit' => 'nullable|in:night,hour',
            'tarif_nuit' => 'nullable|numeric|min:0',
            'tarif_heure' => 'nullable|numeric|min:0',
            'prix_vente' => 'nullable|numeric|min:0',
            'quantite_total' => 'sometimes|integer|min:1',
            'quantite_dispo' => 'sometimes|integer|min:0',
            'livraison_disponible' => 'boolean',
            'frais_livraison' => 'nullable|numeric|min:0',
            'images' => 'nullable|array|max:8',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'existing_photo_ids' => 'nullable|array',
            'existing_photo_ids.*' => 'integer|exists:photos,id',
        ], StoreMaterielleRequest::SEASONAL_RATE_RULES);
    }
}
