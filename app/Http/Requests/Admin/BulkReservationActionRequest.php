<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkReservationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:center,events,materielle,guides',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|in:approve,reject,pending,cancel,delete',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
