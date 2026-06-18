<?php

namespace App\Http\Requests\Center;

use App\Models\ProfileCenterEquipment;
use Illuminate\Foundation\Http\FormRequest;

class StoreProfileCentreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Center basic info
            'name' => 'required|string|max:255',
            'adresse' => 'required|string|max:500',
            'capacite' => 'required|integer|min:1',
            'price_per_night' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'contact_email' => 'required|email',
            'contact_phone' => 'required|string|max:20',
            'manager_name' => 'nullable|string|max:255',
            'established_date' => 'nullable|date',
            'additional_services_description' => 'nullable|string',

            // Services
            'services' => 'nullable|array',
            'services.*.service_category_id' => 'required|exists:service_categories,id',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_available' => 'boolean',
            'services.*.is_standard' => 'boolean',

            // Equipment
            'equipment' => 'nullable|array',
            'equipment.*.type' => 'required|string|in:'.implode(',', array_keys(ProfileCenterEquipment::TYPE_TRANSLATIONS)),
            'equipment.*.is_available' => 'boolean',
        ];
    }
}
