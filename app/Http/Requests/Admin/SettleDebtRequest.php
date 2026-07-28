<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettleDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Omit montant to settle the full outstanding debt.
            'montant' => 'nullable|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
            'password' => 'required|string',
        ];
    }
}
