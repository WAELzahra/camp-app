<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class CreateEventReservationWithPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => 'required|exists:events,id',
            'nbr_place' => 'required|integer|min:1',
            'group_id' => 'required|exists:users,id',
            'promo_code' => 'nullable|string|max:50',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'payment_method' => 'nullable|in:wallet,card,manual',
            'payment_option' => 'nullable|in:full,deposit',
            'group_skill_level' => 'nullable|in:beginner,intermediate,advanced,mixed',
            'trip_purpose' => 'nullable|string|max:255',
            'materials' => 'nullable|array',
            'materials.*.materielle_id' => 'required_with:materials|exists:materielles,id',
            'materials.*.quantite' => 'required_with:materials|integer|min:1',
            'services' => 'nullable|array',
            'services.*.service_id' => 'required_with:services|exists:event_services,id',
            'services.*.quantity' => 'required_with:services|integer|min:1',
            'services.*.notes' => 'nullable|string|max:500',
        ];
    }
}
