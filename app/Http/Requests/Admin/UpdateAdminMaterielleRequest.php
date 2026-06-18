<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminMaterielleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * seasonal_rates may arrive as a JSON string (multipart forms); decode it
     * before validation so the array rules apply — matching the original
     * controller behaviour which merged the decoded value prior to validate().
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('seasonal_rates'))) {
            $this->merge(['seasonal_rates' => json_decode($this->input('seasonal_rates'), true)]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:up,down',
            'nom' => 'sometimes|string|max:255',
            'brand' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'trip_type_tags' => 'sometimes|nullable|array',
            'trip_type_tags.*' => 'string|max:100',
            'weight_kg' => 'sometimes|nullable|numeric|min:0',
            'condition' => 'sometimes|nullable|in:new,like_new,good,fair',
            'rental_unit' => 'sometimes|nullable|in:night,hour',
            'tarif_nuit' => 'sometimes|nullable|numeric|min:0',
            'tarif_heure' => 'sometimes|nullable|numeric|min:0',
            'prix_vente' => 'sometimes|nullable|numeric|min:0',
            'is_rentable' => 'sometimes|boolean',
            'is_sellable' => 'sometimes|boolean',
            'quantite_dispo' => 'sometimes|integer|min:0',
            'seasonal_rates' => 'sometimes|nullable|array|max:12',
            'seasonal_rates.*.name' => 'required|string|max:100',
            'seasonal_rates.*.start_month' => 'required|integer|between:1,12',
            'seasonal_rates.*.start_day' => 'required|integer|between:1,31',
            'seasonal_rates.*.end_month' => 'required|integer|between:1,12',
            'seasonal_rates.*.end_day' => 'required|integer|between:1,31',
            'seasonal_rates.*.price_weekday' => 'required|numeric|min:0',
            'seasonal_rates.*.price_weekend' => 'nullable|numeric|min:0',
        ];
    }
}
