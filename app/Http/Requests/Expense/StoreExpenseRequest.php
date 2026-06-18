<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => 'required|string|max:255',
            'montant' => 'required|numeric|min:0.01',
            'categorie' => 'required|in:transport,hébergement,nourriture,équipement,marketing,maintenance,salaires,location,formation,communication,assurance,autre',
            'status' => 'sometimes|in:brouillon,confirmé,remboursé',
            'date_depense' => 'required|date',
            'event_id' => 'nullable|exists:events,id',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
