<?php

namespace App\Http\Requests\Popup;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'type' => 'sometimes|required|in:info,warning,promotion,update',
            'is_active' => 'sometimes|boolean',
            'popup_kind' => 'sometimes|in:engagement,welcome',
            'target_roles' => 'nullable|array',
            'target_roles.*' => 'integer|min:1|max:6',
            'icon' => 'nullable|string|max:100',
            'cta_label' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:500',
        ];
    }
}
