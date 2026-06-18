<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => 'sometimes|string|max:255',
            'montant' => 'sometimes|numeric|min:0.01',
            'categorie' => 'sometimes|in:transport,hébergement,nourriture,équipement,marketing,maintenance,salaires,location,formation,communication,assurance,autre',
            'status' => 'sometimes|in:brouillon,confirmé,remboursé',
            'date_depense' => 'sometimes|date',
            'event_id' => 'nullable|exists:events,id',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
