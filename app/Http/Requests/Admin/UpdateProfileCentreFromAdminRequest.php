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
            'services' => 'nullable|array',
            'services.*.id' => 'required|integer',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_available' => 'required|boolean',
            'equipment' => 'nullable|array',
            'equipment.*.id' => 'required|integer',
            'equipment.*.is_available' => 'required|boolean',
        ];
    }
}
