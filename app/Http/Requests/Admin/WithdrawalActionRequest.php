<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by approve/complete/reject withdrawal actions — all take the admin
 * password plus an optional note.
 */
class WithdrawalActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'admin_note' => 'nullable|string|max:500',
        ];
    }
}
