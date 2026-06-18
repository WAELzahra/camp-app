<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class InviteToEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'message' => 'nullable|string|max:500',
        ];
    }
}
