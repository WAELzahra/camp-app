<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadUserDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => 'required|string|in:cin,certificat,legal,patente,cin_responsable,cin_commercant,registre',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }
}
