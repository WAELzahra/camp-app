<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCancellationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
            'centre_id' => 'nullable|exists:users,id',
            'grace_period_hours' => 'nullable|integer|min:0|max:8760',
        ];
    }
}
