<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => 'required|in:user,center,group,supplier,zone,platform',
            'target_id' => 'nullable|integer|min:1',
            'location_lat' => 'nullable|numeric|between:-90,90',
            'location_lng' => 'nullable|numeric|between:-180,180',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'reported_user_id' => 'nullable|exists:users,id',
            'page_url' => 'nullable|url|max:500',
            'screenshot' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ];
    }
}
