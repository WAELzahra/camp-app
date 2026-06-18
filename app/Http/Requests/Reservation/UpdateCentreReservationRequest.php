<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCentreReservationRequest extends FormRequest
{
    /**
     * Per-reservation authorization (owner or centre) is checked in the
     * controller, which needs the loaded model; this request only validates.
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
            'service_items' => 'sometimes|array',
            'service_items.*.id' => 'nullable',
            'service_items.*.service_id' => 'required_without:service_items.*.id|exists:profile_center_services,id',
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
