<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCancellationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:centre,materiel,event',
            'name' => 'required|string|max:100',
            'centre_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'grace_period_hours' => 'nullable|integer|min:0|max:8760',
            'tiers' => 'sometimes|array',
            'tiers.*.hours_before' => 'required_with:tiers|integer|min:0',
            'tiers.*.fee_percentage' => 'required_with:tiers|numeric|min:0|max:100',
            'tiers.*.label' => 'nullable|string|max:100',
        ];
    }
}
