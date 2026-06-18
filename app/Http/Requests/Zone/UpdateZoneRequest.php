<?php

namespace App\Http\Requests\Zone;

use Illuminate\Foundation\Http\FormRequest;

class UpdateZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'sometimes|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'region' => 'sometimes|nullable|string|max:255',
            'commune' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'full_description' => 'sometimes|nullable|string',
            'terrain' => 'sometimes|nullable|string|max:255',
            'difficulty' => 'sometimes|in:easy,medium,hard',
            'lat' => 'sometimes|numeric|between:-90,90',
            'lng' => 'sometimes|numeric|between:-180,180',
            'adresse' => 'sometimes|nullable|string|max:500',
            'distance' => 'sometimes|nullable|string|max:100',
            'altitude' => 'sometimes|nullable|string|max:100',
            'access_type' => 'sometimes|nullable|in:road,trail,boat,mixed',
            'accessibility' => 'sometimes|nullable|string|max:255',
            'best_season' => 'sometimes|nullable|array',
            'activities' => 'sometimes|nullable|array',
            'facilities' => 'sometimes|nullable|array',
            'rules' => 'sometimes|nullable|array',
            'contact_phone' => 'sometimes|nullable|string|max:50',
            'contact_email' => 'sometimes|nullable|email|max:255',
            'contact_website' => 'sometimes|nullable|url|max:255',
            'max_capacity' => 'sometimes|nullable|integer|min:1',
            'danger_level' => 'sometimes|in:low,moderate,high,extreme',
            'is_public' => 'sometimes|boolean',
            'status' => 'sometimes|boolean',
            'is_protected_area' => 'sometimes|boolean',
            'centre_id' => 'sometimes|nullable|exists:camping_centres,id',
            'is_beginner_friendly' => 'sometimes|boolean',
            'terrain_type' => 'sometimes|nullable|in:forest,mountain,desert,coastal,plain,wetland',
            'min_temp_celsius' => 'sometimes|nullable|integer|between:-60,60',
            'max_temp_celsius' => 'sometimes|nullable|integer|between:-60,60',
        ];
    }
}
