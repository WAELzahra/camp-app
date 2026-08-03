<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampingCentreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'adresse' => 'required|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'road_condition' => 'nullable|in:paved,unpaved_2wd_ok,4x4_required,hiking_only',
            'road_access_notes' => 'nullable|string|max:2000',
            'public_transport_accessible' => 'nullable|boolean',
            'public_transport_notes' => 'nullable|string|max:2000',
            'public_transport_final_leg' => 'nullable|in:at_entrance,short_walk,additional_transport_needed',
            'type' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'is_partner' => 'nullable|boolean',
            'validation_status' => 'nullable|in:pending,approved,rejected',
            'user_id' => 'nullable|exists:users,id',
            'photos' => 'nullable|array',
            'photos.*' => 'file|image|max:5120',
            'cover_index' => 'nullable|integer|min:0',
        ];
    }
}
