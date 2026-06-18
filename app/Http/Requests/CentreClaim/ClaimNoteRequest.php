<?php

namespace App\Http\Requests\CentreClaim;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by approve and revoke — both take an optional admin note.
 */
class ClaimNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_note' => 'nullable|string|max:1000',
        ];
    }
}
