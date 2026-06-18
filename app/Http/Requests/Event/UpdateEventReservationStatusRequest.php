<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventReservationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'en_attente_paiement',
                    'confirmée',
                    'en_attente_validation',
                    'refusée',
                    'annulée_par_utilisateur',
                    'annulée_par_organisateur',
                    'remboursement_en_attente',
                    'remboursée_partielle',
                    'remboursée_totale',
                ]),
            ],
        ];
    }
}
