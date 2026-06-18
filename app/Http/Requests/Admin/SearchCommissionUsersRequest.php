<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SearchCommissionUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'sometimes|string|max:100',
            'role_id' => 'sometimes|integer',
            'exclude_rule_id' => 'sometimes|integer',
        ];
    }
}
