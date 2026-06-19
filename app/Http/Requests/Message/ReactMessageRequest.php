<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class ReactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction' => 'required|string|max:50|in:👍,❤️,😂,😮,😢,😡,🎉,👏,🔥,✅,⭐',
        ];
    }
}
