<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'sexe' => ['nullable', 'string', 'max:10'],
            'langue' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'string', 'exists:roles,name'],
            'invitation_token' => ['nullable', 'string', 'max:128'],

            // Make all these fields nullable instead of required
            'adresse' => ['nullable', 'string', 'max:500'],
            'capacite' => ['nullable', 'integer', 'min:1'],
            'services_offerts' => ['nullable', 'string'],
            'price_per_night' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'nom_groupe' => ['nullable', 'string', 'max:255'],
            'cin_responsable' => ['nullable', 'string', 'max:50'],
            'experience' => ['nullable', 'integer', 'min:0'],
            'tarif' => ['nullable', 'numeric', 'min:0'],
            'zone_travail' => ['nullable', 'string', 'max:255'],
            'cin' => ['nullable', 'string', 'max:50'],
            'cin_fournisseur' => ['nullable', 'string', 'max:50'],
            'interval_prix' => ['nullable', 'string', 'max:100'],
            'product_category' => ['nullable', 'string', 'max:255'],
            'legal_document_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'center_images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:5120'],
        ];
    }
}
