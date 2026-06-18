<?php

namespace App\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;

class StoreCenterServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_category_id' => 'required|exists:service_categories,id',
            'price' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_available' => 'boolean',
            'is_standard' => 'boolean',
            'min_quantity' => 'integer|min:1',
            'max_quantity' => 'nullable|integer|min:1',
        ];
    }
}
