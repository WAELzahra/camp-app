<?php

namespace App\Http\Requests\Popup;

use Illuminate\Foundation\Http\FormRequest;

class StorePopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Tutorial popups are auto-titled server-side (admin only picks roles + video link)
            'title' => 'required_unless:popup_kind,tutorial|nullable|string|max:255',
            'content' => 'required_unless:popup_kind,tutorial|nullable|string',
            'type' => 'required|in:info,warning,promotion,update',
            'is_active' => 'boolean',
            'popup_kind' => 'in:engagement,welcome,tutorial',
            'target_roles' => 'nullable|array',
            'target_roles.*' => 'integer|min:1|max:6',
            'icon' => 'nullable|string|max:100',
            'cta_label' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:500',
            'video_url' => 'required_if:popup_kind,tutorial|nullable|string|max:500',
        ];
    }
}
