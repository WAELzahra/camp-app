<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'commission_camper' => 'sometimes|numeric|min:0|max:50',
            'commission_center' => 'sometimes|numeric|min:0|max:50',
            'commission_group' => 'sometimes|numeric|min:0|max:50',
            'commission_supplier' => 'sometimes|numeric|min:0|max:50',
            'commission_guide' => 'sometimes|numeric|min:0|max:50',
            'service_fee_camper' => 'sometimes|numeric|min:0|max:50',
            'withdrawal_fee_percentage' => 'sometimes|numeric|min:0|max:50',
            'withdrawal_min_amount' => 'sometimes|numeric|min:0',
            'withdrawal_processing_days' => 'sometimes|integer|min:1|max:30',
            'withdrawal_allowed_days' => 'sometimes|array',
            'withdrawal_allowed_days.*' => 'integer|min:1|max:7',
            'withdrawal_enabled' => 'sometimes|boolean',
            'gateway_konnect_enabled' => 'sometimes|boolean',
            'gateway_flouci_enabled' => 'sometimes|boolean',
        ];
    }
}
