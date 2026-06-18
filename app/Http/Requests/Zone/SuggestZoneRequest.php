<?php

namespace App\Http\Requests\Zone;

use Illuminate\Foundation\Http\FormRequest;

class SuggestZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'commune' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'full_description' => 'nullable|string',
            'terrain' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'adresse' => 'nullable|string|max:500',
            'distance' => 'nullable|string|max:100',
            'altitude' => 'nullable|string|max:100',
            'access_type' => 'nullable|in:road,trail,boat,mixed',
            'accessibility' => 'nullable|string|max:255',
            'best_season' => 'nullable|array',
            'activities' => 'nullable|array',
            'facilities' => 'nullable|array',
            'rules' => 'nullable|array',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'contact_website' => 'nullable|url|max:255',
            'max_capacity' => 'nullable|integer|min:1',
            'centre_id' => 'nullable|exists:camping_centres,id',
            'is_protected_area' => 'nullable|boolean',
            'is_beginner_friendly' => 'nullable|boolean',
            'terrain_type' => 'nullable|in:forest,mountain,desert,coastal,plain,wetland',
            'min_temp_celsius' => 'nullable|integer|between:-60,60',
            'max_temp_celsius' => 'nullable|integer|between:-60,60',
            'photos' => 'nullable|array',
            'photos.*' => 'string|max:255',
        ];
    }
}
