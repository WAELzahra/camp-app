<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:service_categories',
            'description' => 'nullable|string',
            'is_standard' => 'boolean',
            'suggested_price' => 'required|numeric|min:0',
            'min_price' => 'required|numeric|min:0|lte:suggested_price',
            'unit' => 'required|string|max:50',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
