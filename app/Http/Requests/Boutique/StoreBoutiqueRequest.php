<?php

namespace App\Http\Requests\Boutique;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoutiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom_boutique' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'nom_boutique.required' => 'Le nom de la boutique est obligatoire.',
        ];
    }
}
