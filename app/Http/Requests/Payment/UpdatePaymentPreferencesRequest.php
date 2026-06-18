<?php

namespace App\Http\Requests\Payment;

use App\Models\PlatformSetting as PS;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minPct = (int) PS::get('deposit_min_percentage', 20);
        $maxPct = (int) PS::get('deposit_max_percentage', 80);

        return [
            'accepts_deposits' => 'required|boolean',
            'deposit_percentage' => "required_if:accepts_deposits,true|integer|min:{$minPct}|max:{$maxPct}",
        ];
    }
}
