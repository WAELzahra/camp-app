<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MergeZonesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'primary_zone_id' => 'required|exists:CampingZone,id',
            'secondary_zone_id' => 'required|exists:CampingZone,id|different:primary_zone_id',
        ];
    }
}
