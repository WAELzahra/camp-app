<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'pricing_unit' => 'sometimes|string|max:100',
            'max_quantity' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
