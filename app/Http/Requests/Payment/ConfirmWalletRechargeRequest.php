<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmWalletRechargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The admin may correct the credited amount to the amount actually received.
        return [
            'amount' => 'sometimes|nullable|numeric|min:0.01|max:100000',
        ];
    }
}
