<?php

namespace App\Http\Requests\CentreClaim;

use Illuminate\Foundation\Http\FormRequest;

class RejectClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_note' => 'required|string|min:5|max:1000',
        ];
    }
}
