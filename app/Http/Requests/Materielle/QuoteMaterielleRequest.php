<?php

namespace App\Http\Requests\Materielle;

use Illuminate\Foundation\Http\FormRequest;

class QuoteMaterielleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'quantite' => 'nullable|integer|min:1',
            'hours' => 'nullable|integer|min:1|max:24',
        ];
    }
}
