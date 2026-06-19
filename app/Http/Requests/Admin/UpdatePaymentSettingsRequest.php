<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'payment_link_flouci' => 'sometimes|nullable|string|max:2000',
            'manual_payment_enabled' => 'sometimes|boolean',
            'deposit_min_percentage' => 'sometimes|integer|min:1|max:99',
            'deposit_max_percentage' => 'sometimes|integer|min:1|max:99',
            'deposit_min_total' => 'sometimes|integer|min:0',
            'bank_transfer_enabled' => 'sometimes|boolean',
            'bank_account_holder' => 'sometimes|nullable|string|max:255',
            'bank_account_bank_name' => 'sometimes|nullable|string|max:255',
            'bank_account_rib' => 'sometimes|nullable|string|max:60',
            'bank_account_iban' => 'sometimes|nullable|string|max:60',
            'bank_account_instructions' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
