<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfilePhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'album_title' => 'nullable|string|max:255',
            'album_description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'photos.*.max' => 'Each image must be under 5 MB.',
        ];
    }
}
