<?php

namespace App\Http\Requests\Materielle;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaterielleRequest extends FormRequest
{
    /** Seasonal-rate sub-rules, shared with the update request. */
    public const SEASONAL_RATE_RULES = [
        'seasonal_rates' => 'nullable|array|max:12',
        'seasonal_rates.*.name' => 'required|string|max:100',
        'seasonal_rates.*.start_month' => 'required|integer|between:1,12',
        'seasonal_rates.*.start_day' => 'required|integer|between:1,31',
        'seasonal_rates.*.end_month' => 'required|integer|between:1,12',
        'seasonal_rates.*.end_day' => 'required|integer|between:1,31',
        'seasonal_rates.*.price_weekday' => 'required|numeric|min:0',
        'seasonal_rates.*.price_weekend' => 'nullable|numeric|min:0',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'category_id' => 'required|exists:materielles_categories,id',
            'nom' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'description' => 'required|string',
            'trip_type_tags' => 'nullable|array',
            'trip_type_tags.*' => 'string|max:100',
            'weight_kg' => 'nullable|numeric|min:0',
            'condition' => 'nullable|in:new,like_new,good,fair',
            'is_rentable' => 'boolean',
            'is_sellable' => 'boolean',
            'rental_unit' => 'nullable|in:night,hour',
            'tarif_nuit' => 'nullable|numeric|min:0',
            'tarif_heure' => 'nullable|numeric|min:0',
            'prix_vente' => 'nullable|numeric|min:0',
            'quantite_total' => 'required|integer|min:1',
            'quantite_dispo' => 'required|integer|min:0',
            'livraison_disponible' => 'boolean',
            'frais_livraison' => 'nullable|numeric|min:0',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], self::SEASONAL_RATE_RULES);
    }
}
