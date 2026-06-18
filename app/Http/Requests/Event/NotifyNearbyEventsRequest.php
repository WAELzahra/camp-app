<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class NotifyNearbyEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:100',
        ];
    }
}
