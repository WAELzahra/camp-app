<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterielleReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'materielle_id' => 'required|exists:materielles,id',
            'fournisseur_id' => 'required|exists:users,id',
            'type_reservation' => 'required|in:location,achat',
            'date_debut' => 'required_if:type_reservation,location|nullable|date|after_or_equal:today',
            'date_fin' => [
                Rule::requiredIf(fn () => $this->type_reservation === 'location' && !$this->filled('hours')),
                'nullable', 'date', 'after_or_equal:date_debut',
            ],
            'hours' => 'nullable|integer|min:1|max:24',
            'quantite' => 'required|integer|min:1',
            // Kept for backward compatibility but no longer trusted — the
            // total is recomputed server-side from the stored rates.
            'montant_total' => 'nullable|numeric|min:0',
            'mode_livraison' => 'required|in:pickup,delivery',
            'adresse_livraison' => 'required_if:mode_livraison,delivery|nullable|string|max:500',
            'frais_livraison' => 'nullable|numeric|min:0',
            'promo_code' => 'nullable|string|max:50',
            'payment_method' => 'nullable|in:wallet,card,manual',
            'payment_option' => 'nullable|in:full,deposit',
        ];
    }
}
