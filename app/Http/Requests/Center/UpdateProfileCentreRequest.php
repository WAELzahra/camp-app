<?php

namespace App\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileCentreRequest extends FormRequest
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
            'disponibilite' => 'boolean',

            // Profile bio
            'bio' => 'nullable|string',
        ];
    }
}
