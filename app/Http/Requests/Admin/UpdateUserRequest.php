<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // users table
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($this->route('id'))],
            'phone_number' => 'sometimes|string|max:20|nullable',
            'ville' => 'sometimes|string|nullable',
            'birthdate' => 'sometimes|date|nullable',
            'gender' => 'sometimes|string|in:male,female,other|nullable',
            'languages' => 'sometimes|string|nullable',
            'avatar' => 'sometimes|string|nullable',
            'cover_image' => 'sometimes|string|nullable',
            'role_id' => 'sometimes|exists:roles,id',
            'is_active' => 'sometimes|boolean',
            'first_login' => 'sometimes|boolean',
            'nombre_signalement' => 'sometimes|integer',

            // profiles table
            'bio' => 'sometimes|string|nullable',
            'city' => 'sometimes|string|nullable',
            'address' => 'sometimes|string|nullable',
            'cin_path' => 'sometimes|string|nullable',
            'activities' => 'sometimes|string|nullable',
            'is_public' => 'sometimes|boolean',

            // profile_guides
            'experience' => 'sometimes|integer|nullable',
            'tarif' => 'sometimes|numeric|nullable',
            'zone_travail' => 'sometimes|string|nullable',
            'certificat_path' => 'sometimes|string|nullable',
            'certificat_type' => 'sometimes|string|nullable',
            'certificat_expiration' => 'sometimes|date|nullable',

            // profile_centres
            'centre_name' => 'sometimes|string|nullable',
            'capacity' => 'sometimes|integer|nullable',
            'price_per_night' => 'sometimes|numeric|nullable',
            'category' => 'sometimes|string|nullable',
            'disponibilite' => 'sometimes|boolean',
            'legal_document' => 'sometimes|string|nullable',
            'document_legal_type' => 'sometimes|string|nullable',
            'document_legal_expiration' => 'sometimes|date|nullable',
            'contact_email' => 'sometimes|email|nullable',
            'contact_phone' => 'sometimes|string|nullable',
            'manager_name' => 'sometimes|string|nullable',
            'established_date' => 'sometimes|date|nullable',
            'latitude' => 'sometimes|numeric|nullable',
            'longitude' => 'sometimes|numeric|nullable',

            // profile_groupes
            'nom_groupe' => 'sometimes|string|nullable',
            'patente_path' => 'sometimes|string|nullable',

            // profile_fournisseurs
            'intervale_prix' => 'sometimes|string|nullable',
            'product_category' => 'sometimes|string|nullable',
        ];
    }
}
