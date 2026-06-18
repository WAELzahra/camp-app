<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCancellationTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours_before' => 'required|integer|min:0',
            'fee_percentage' => 'required|numeric|min:0|max:100',
            'label' => 'nullable|string|max:100',
        ];
    }
}
