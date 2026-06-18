<?php

namespace App\Http\Requests\Center;

use App\Models\ProfileCenterEquipment;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCenterEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment' => 'required|array',
            'equipment.*.type' => 'required|string|in:'.implode(',', array_keys(ProfileCenterEquipment::TYPE_TRANSLATIONS)),
            'equipment.*.is_available' => 'boolean',
        ];
    }
}
