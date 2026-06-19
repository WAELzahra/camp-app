<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => 'sometimes|string|unique:notification_templates,key,'.$this->route('id'),
            'name' => 'sometimes|string|max:255',
            'subject' => 'nullable|string|max:255',
            'content' => 'sometimes|string',
            'variables' => 'nullable|array',
            'channels' => 'nullable|array',
            'priority' => 'sometimes|in:low,medium,high,critical',
        ];
    }
}
