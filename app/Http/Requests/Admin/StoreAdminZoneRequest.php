<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'city' => 'nullable|string|max:100',
            'type_activitee' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'full_description' => 'nullable|string',
            'terrain' => 'nullable|string|max:100',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'adresse' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'commune' => 'nullable|string|max:100',
            'accessibility' => 'nullable|string|max:255',
            'altitude' => 'nullable|string|max:50',
            'distance' => 'nullable|string|max:100',
            'danger_level' => 'nullable|in:low,moderate,high,extreme',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'max_capacity' => 'nullable|integer|min:1',
            'access_type' => 'nullable|string|max:100',
            'centre_id' => 'nullable|exists:camping_centres,id',
            'activities' => 'nullable|array',
            'activities.*' => 'string',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'best_season' => 'nullable|array',
            'best_season.*' => 'string',
            'rules' => 'nullable|array',
            'rules.*' => 'string',
            'contact_phone' => 'nullable|string|max:30',
            'contact_email' => 'nullable|email|max:150',
            'contact_website' => 'nullable|url|max:255',
            'is_public' => 'nullable|boolean',
            'status' => 'nullable|boolean',
            'is_protected_area' => 'nullable|boolean',
            'is_closed' => 'nullable|boolean',
            'closure_reason' => 'nullable|string',
            'closure_start' => 'nullable|date',
            'closure_end' => 'nullable|date|after_or_equal:closure_start',
        ];
    }
}
