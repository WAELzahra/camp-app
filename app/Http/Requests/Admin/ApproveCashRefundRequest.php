<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Unlike the default credit-based refund, a direct cash refund bypasses the
 * platform credit entirely and is meant only for legally-mandated exceptions —
 * the reason is required so there's an audit trail for why this path was used.
 */
class ApproveCashRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'reason' => 'required|string|max:500',
        ];
    }
}
