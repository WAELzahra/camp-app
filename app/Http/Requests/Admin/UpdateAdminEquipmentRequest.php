<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_available' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:500',
        ];
    }
}
