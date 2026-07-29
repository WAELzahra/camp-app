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
            'payment_method' => 'nullable|in:wallet,card,manual,cash',
            'payment_option' => 'nullable|in:full,deposit',
            'transfer_reference' => 'nullable|string|max:120',
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

    /**
     * Cash-at-centre only makes sense for a near-term event: it skips the usual
     * payment-before-review flow (organizer accepts first, cash changes hands at
     * the event), so a booking made weeks out with no payment on file would be
     * cancellable with nothing to enforce. Capped to a 48h window server-side,
     * checked against the event's own start_date (there's no date field on the
     * reservation itself — the date belongs to the event).
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($this->input('payment_method') === 'manual'
                && \App\Services\ManualPaymentService::bankTransferEnabled()
                && !\App\Services\ManualPaymentService::isEnabled()
                && !$this->filled('transfer_reference')) {
                $validator->errors()->add(
                    'transfer_reference',
                    'La référence de votre virement bancaire est requise pour confirmer la réservation.'
                );
            }

            if ($this->input('payment_method') !== 'cash') {
                return;
            }

            $event = \App\Models\Events::find($this->input('event_id'));
            if (!$event || !$event->start_date) {
                return; // event_id's own `exists` rule already fails an invalid event
            }

            if (!\App\Services\CashPaymentService::isDateEligible($event->start_date)) {
                $windowHours = \App\Services\CashPaymentService::ELIGIBILITY_WINDOW_HOURS;
                $validator->errors()->add(
                    'payment_method',
                    "Le paiement en espèces n'est disponible qu'à partir d'aujourd'hui et jusqu'à {$windowHours}h à l'avance."
                );
            }
        });
    }
}
