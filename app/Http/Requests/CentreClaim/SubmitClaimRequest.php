<?php

namespace App\Http\Requests\CentreClaim;

use Illuminate\Foundation\Http\FormRequest;

class SubmitClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|min:10|max:2000',
            'proof_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }
}
