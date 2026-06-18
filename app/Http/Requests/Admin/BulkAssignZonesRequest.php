<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignZonesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_ids' => 'required|array|min:1',
            'zone_ids.*' => 'integer|exists:CampingZone,id',
            'centre_id' => 'required|exists:camping_centres,id',
        ];
    }
}
