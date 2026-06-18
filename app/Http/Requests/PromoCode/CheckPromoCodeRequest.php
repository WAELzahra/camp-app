<?php

namespace App\Http\Requests\PromoCode;

use Illuminate\Foundation\Http\FormRequest;

class CheckPromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
            'reservation_type' => 'required|in:centre,materiel,event',
            'price' => 'required|numeric|min:0',
        ];
    }
}
