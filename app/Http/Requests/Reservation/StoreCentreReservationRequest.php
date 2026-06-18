<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class StoreCentreReservationRequest extends FormRequest
{
    /**
     * Authorization is enforced by route middleware (`campeur`); this request
     * only validates input, so it always authorizes.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'centre_id' => 'required|exists:users,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'nbr_place' => 'required|integer|min:1',
            'note' => 'nullable|string',
            'group_skill_level' => 'nullable|in:beginner,intermediate,advanced,mixed',
            'trip_purpose' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:card,wallet,manual',
            'payment_option' => 'nullable|in:full,deposit',
            'promo_code' => 'nullable|string|max:50',
            'service_items' => 'required|array|min:1',
            'service_items.*.profile_center_service_id' => 'required|exists:profile_center_services,id',
            'service_items.*.quantity' => 'required|integer|min:1',
            'service_items.*.unit_price' => 'required|numeric|min:0',
            'service_items.*.service_name' => 'required|string',
            'service_items.*.unit' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'centre_id.required' => 'The centre ID is required.',
            'centre_id.exists' => 'The selected centre does not exist.',
            'date_debut.required' => 'Start date is required.',
            'date_fin.required' => 'End date is required.',
            'date_fin.after_or_equal' => 'The end date must be after or equal to the start date.',
            'nbr_place.required' => 'The number of places is required.',
            'service_items.required' => 'At least one service item is required.',
            'service_items.*.profile_center_service_id.required' => 'Service ID is required for each item.',
            'service_items.*.quantity.required' => 'Quantity is required for each service.',
        ];
    }
}
