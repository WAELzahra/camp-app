<?php

namespace App\Http\Requests\Center;

use App\Models\ProfileCenterEquipment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileCentreServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'services' => 'nullable|array',
            'services.*.service_category_id' => 'required|exists:service_categories,id',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_available' => 'boolean',
            'services.*.is_standard' => 'boolean',
            'services.*.min_quantity' => 'nullable|integer|min:1',
            'services.*.max_quantity' => 'nullable|integer|min:1',

            'equipment' => 'nullable|array',
            'equipment.*.type' => 'required|string|in:'.implode(',', array_keys(ProfileCenterEquipment::TYPE_TRANSLATIONS)),
            'equipment.*.is_available' => 'boolean',
            'equipment.*.notes' => 'nullable|string',
        ];
    }
}
