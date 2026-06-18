<?php

namespace App\Http\Requests\Annonce;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnonceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|string|max:255',
            'activities' => 'nullable|array',
            'latitude' => 'nullable|string|max:20',
            'longitude' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'auto_archive' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'existing_images' => 'nullable|string',
            'cover_photo_id' => 'nullable|integer',
            'cover_index' => 'nullable|integer|min:0',
        ];
    }
}
