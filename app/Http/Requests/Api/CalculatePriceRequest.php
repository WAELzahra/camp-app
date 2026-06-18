<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'center_id' => 'required|exists:profile_centres,id',
            'nights' => 'required|integer|min:1',
            'people' => 'required|integer|min:1',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:service_categories,id',
            'services.*.quantity' => 'required|integer|min:1',
        ];
    }
}
