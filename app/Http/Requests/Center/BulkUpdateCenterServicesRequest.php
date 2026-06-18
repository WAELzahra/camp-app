<?php

namespace App\Http\Requests\Center;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCenterServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'services' => 'required|array',
            'services.*.service_category_id' => 'required|exists:service_categories,id',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_available' => 'boolean',
            'services.*.is_standard' => 'boolean',
        ];
    }
}
