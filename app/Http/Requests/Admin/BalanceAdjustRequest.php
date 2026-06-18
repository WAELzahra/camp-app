<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BalanceAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01|max:10000',
            'description' => 'required|string|min:3|max:255',
        ];
    }
}
