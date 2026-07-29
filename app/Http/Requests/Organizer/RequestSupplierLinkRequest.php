<?php

namespace App\Http\Requests\Organizer;

use Illuminate\Foundation\Http\FormRequest;

class RequestSupplierLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // User.id is hidden from all API output (see User::$hidden — API
            // clients only ever see uuid), so the frontend can never actually
            // supply a numeric id here. Look up by uuid instead.
            'supplier_uuid' => 'required|uuid|exists:users,uuid',
        ];
    }
}
