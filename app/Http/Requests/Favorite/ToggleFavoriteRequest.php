<?php

namespace App\Http\Requests\Favorite;

use Illuminate\Foundation\Http\FormRequest;

class ToggleFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:profile,centre,zone,equipment,annonce'],
            'target_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
