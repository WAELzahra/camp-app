<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCancellationTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours_before' => 'sometimes|integer|min:0',
            'fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'label' => 'nullable|string|max:100',
        ];
    }
}
