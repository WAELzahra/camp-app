<?php

namespace App\Http\Requests\Signal;

use Illuminate\Foundation\Http\FormRequest;

class StoreSignalementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contenu' => 'required|string|max:255',
            'photo' => 'nullable|image|max:5120',
        ];
    }
}
