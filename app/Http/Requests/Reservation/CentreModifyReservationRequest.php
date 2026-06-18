<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class CentreModifyReservationRequest extends FormRequest
{
    /**
     * Centre ownership + pending-status checks live in the controller (they need
     * the loaded model); this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
            'nbr_place' => 'sometimes|integer|min:1',
            'note' => 'nullable|string',
            'modification_reason' => 'nullable|string|max:500',
            'service_items' => 'sometimes|array',
            'service_items.*.id' => 'nullable',
            'service_items.*.service_id' => 'required|exists:profile_center_services,id',
            'service_items.*.service_name' => 'sometimes|string',
            'service_items.*.quantity' => 'required|integer|min:1',
            'service_items.*.unit_price' => 'required|numeric|min:0',
            'service_items.*.unit' => 'sometimes|string',
            'service_items.*.notes' => 'nullable|string',
            'service_items.*.is_new' => 'sometimes|boolean',
            'service_items.*.is_removed' => 'sometimes|boolean',
        ];
    }
}
