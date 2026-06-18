<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'montant' => 'required|numeric|min:0.01',
            'type' => 'required|in:credit,debit',
            'note' => 'nullable|string|max:500',
            'password' => 'required|string',
        ];
    }
}
