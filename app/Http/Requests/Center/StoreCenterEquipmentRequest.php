<?php

namespace App\Http\Requests\Center;

use App\Models\ProfileCenterEquipment;
use Illuminate\Foundation\Http\FormRequest;

class StoreCenterEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:'.implode(',', array_keys(ProfileCenterEquipment::TYPE_TRANSLATIONS)),
            'is_available' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
