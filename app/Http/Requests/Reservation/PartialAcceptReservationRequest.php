<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class PartialAcceptReservationRequest extends FormRequest
{
    /**
     * Centre ownership is checked in the controller (needs the loaded model);
     * this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejected_services' => 'required|array|min:1',
            'rejected_services.*.service_item_id' => 'required|exists:reservation_service_items,id',
            'rejected_services.*.reason' => 'required|string|max:500',
            'general_reason' => 'nullable|string|max:500',
        ];
    }
}
