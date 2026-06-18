<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by the centre and zone admin photo-upload endpoints — both accept an
 * array of image files plus an optional cover index.
 */
class AddPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => 'required|array',
            'photos.*' => 'file|image|max:5120',
            'cover_index' => 'nullable|integer',
        ];
    }
}
