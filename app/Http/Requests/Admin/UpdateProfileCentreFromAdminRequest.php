<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileCentreFromAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price_per_night' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'host_type' => 'nullable|string|in:camping,gite,maison,auberge,ecolodge',
            'capacite' => 'nullable|integer|min:0',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'manager_name' => 'nullable|string|max:255',
            'disponibilite' => 'nullable|boolean',
            'road_condition' => 'nullable|in:paved,unpaved_2wd_ok,4x4_required,hiking_only',
            'road_access_notes' => 'nullable|string|max:2000',
            'public_transport_accessible' => 'nullable|boolean',
            'public_transport_notes' => 'nullable|string|max:2000',
            'public_transport_final_leg' => 'nullable|in:at_entrance,short_walk,additional_transport_needed',
            'services' => 'nullable|array',
            'services.*.id' => 'required|integer',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_available' => 'required|boolean',
            'services.*.name' => 'sometimes|nullable|string|max:255',
            'services.*.description' => 'sometimes|nullable|string',
            'services.*.unit' => 'sometimes|nullable|string|max:50',
            'services.*.nbr_place' => 'sometimes|nullable|integer|min:1',
            'services.*.is_refundable' => 'sometimes|nullable|boolean',
            'equipment' => 'nullable|array',
            'equipment.*.id' => 'required|integer',
            'equipment.*.is_available' => 'required|boolean',
            'house_rules_notes' => 'nullable|string|max:2000',
            'rules' => 'nullable|array',
            'rules.*.type' => 'required|in:pets,alcohol,smoking,loud_music,unmarried_couples,campfires,generators,outside_visitors',
            'rules.*.is_allowed' => 'required|boolean',
            'rules.*.notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
