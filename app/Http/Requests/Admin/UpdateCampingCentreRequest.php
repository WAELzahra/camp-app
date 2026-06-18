<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampingCentreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'sometimes|string|max:255',
            'adresse' => 'sometimes|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'lat' => 'sometimes|numeric',
            'lng' => 'sometimes|numeric',
            'type' => 'sometimes|string',
            'image' => 'nullable|image|max:5120',
            'description' => 'nullable|string',
            'status' => 'sometimes|boolean',
            'is_partner' => 'sometimes|boolean',
            'validation_status' => 'nullable|in:pending,approved,rejected',
        ];
    }
}
