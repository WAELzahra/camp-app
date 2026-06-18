<?php

namespace App\Http\Requests\CampingCentre;

use Illuminate\Foundation\Http\FormRequest;

class SuggestCentreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string',
            'adresse' => 'required|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'type' => 'nullable|string',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
        ];
    }
}
