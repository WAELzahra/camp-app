<?php

namespace App\Observers;

use App\Models\Reservations_centre;
use App\Services\AI\BehavioralProfileService;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates the behavioral profile cache when a centre reservation
 * transitions to 'approved'. A completed booking is the strongest
 * signal available — the profile should reflect it immediately.
 */
class ReservationCentreObserver
{
    public function __construct(
        private readonly BehavioralProfileService $behavioralProfileService,
    ) {}

    public function updated(Reservations_centre $reservation): void
    {
        if (! $reservation->wasChanged('status')) {
            return;
        }

        if ($reservation->status !== 'approved') {
            return;
        }

        $userId = $reservation->user_id;
        if (! $userId) {
            return;
        }

        $this->behavioralProfileService->invalidate($userId);

        Log::info('behavioral_profile_invalidated', [
            'trigger'        => 'reservation_approved',
            'user_id'        => $userId,
            'reservation_id' => $reservation->id,
        ]);
    }
}
