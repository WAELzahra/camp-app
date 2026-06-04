<?php

namespace App\Observers;

use App\Models\Reservations_materielles;
use App\Services\AI\BehavioralProfileService;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates the behavioral profile cache when a gear rental reaches
 * 'confirmed' or 'returned'. Either status signals that gear was actively
 * used, which feeds the inferred_gear_needed and budget signals.
 */
class ReservationMaterielleObserver
{
    public function __construct(
        private readonly BehavioralProfileService $behavioralProfileService,
    ) {}

    public function updated(Reservations_materielles $reservation): void
    {
        if (! $reservation->wasChanged('status')) {
            return;
        }

        if (! in_array($reservation->status, ['confirmed', 'returned'], true)) {
            return;
        }

        $userId = $reservation->user_id;
        if (! $userId) {
            return;
        }

        $this->behavioralProfileService->invalidate($userId);

        Log::info('behavioral_profile_invalidated', [
            'trigger'        => 'gear_rental_' . $reservation->status,
            'user_id'        => $userId,
            'reservation_id' => $reservation->id,
        ]);
    }
}
