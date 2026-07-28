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
            'payment_method' => 'nullable|in:wallet,card,manual,cash',
            'payment_option' => 'nullable|in:full,deposit',
        ];
    }

    /**
     * Cash-at-centre only makes sense for a near-term pickup: it skips the usual
     * payment-before-review flow (supplier accepts first, cash changes hands on
     * pickup/delivery), so a rental booked weeks out with no payment on file would
     * be cancellable with nothing to enforce. Capped to a 48h window server-side.
     * Purchases ('achat') have no future date at all — nothing to cap, so cash is
     * allowed unconditionally for those when the admin toggle is on.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($this->input('payment_method') !== 'cash' || $this->input('type_reservation') !== 'location') {
                return;
            }

            $dateDebut = $this->input('date_debut');
            if (!$dateDebut) {
                return; // date_debut's own conditional `required_if` rule already fails this request
            }

            if (!\App\Services\CashPaymentService::isDateEligible($dateDebut)) {
                $windowHours = \App\Services\CashPaymentService::ELIGIBILITY_WINDOW_HOURS;
                $validator->errors()->add(
                    'payment_method',
                    "Le paiement en espèces n'est disponible qu'à partir d'aujourd'hui et jusqu'à {$windowHours}h à l'avance."
                );
            }
        });
    }
}
