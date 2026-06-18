<?php

namespace App\Http\Requests\BankInfo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBankInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => 'nullable|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:60',
            'flouci_phone' => 'nullable|string|max:30',
            'card_last4' => 'nullable|string|max:4',
        ];
    }
}
