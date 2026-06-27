<?php

namespace App\Http\Requests\Legal;

use Illuminate\Foundation\Http\FormRequest;

class AcceptLegalDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate is auth:sanctum on the route
    }

    public function rules(): array
    {
        return [
            'document_ids'   => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'integer', 'exists:legal_documents,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_ids.required'   => 'You must accept the legal documents.',
            'document_ids.*.exists'   => 'One or more selected documents do not exist.',
        ];
    }
}
