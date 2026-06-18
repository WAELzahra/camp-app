<?php

namespace App\Http\Requests\Annonce;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnonceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'required|string|max:255',
            'activities' => 'nullable|array',
            'latitude' => 'nullable|string|max:20',
            'longitude' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'auto_archive' => 'boolean',
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
            'cover_index' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'description.required' => 'La description est obligatoire.',
            'type.required' => 'Le type d\'annonce est obligatoire.',
            'images.required' => 'Au moins une image est obligatoire.',
            'images.min' => 'Au moins une image est obligatoire.',
            'images.*.image' => 'Le fichier doit être une image.',
            'images.*.mimes' => 'L\'image doit être au format jpeg, png, jpg ou gif.',
            'images.*.max' => 'L\'image ne doit pas dépasser 5 Mo.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
