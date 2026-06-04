<?php

namespace App\Observers;

use App\Models\Feedback;
use App\Services\AI\BehavioralProfileService;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates the behavioral profile cache when a feedback is approved.
 * Approved zone feedbacks feed the inferred_terrain_preference signal.
 */
class FeedbackObserver
{
    public function __construct(
        private readonly BehavioralProfileService $behavioralProfileService,
    ) {}

    public function updated(Feedback $feedback): void
    {
        if (! $feedback->wasChanged('status')) {
            return;
        }

        if ($feedback->status !== 'approved') {
            return;
        }

        $userId = $feedback->user_id;
        if (! $userId) {
            return;
        }

        $this->behavioralProfileService->invalidate($userId);

        Log::info('behavioral_profile_invalidated', [
            'trigger'     => 'feedback_approved',
            'user_id'     => $userId,
            'feedback_id' => $feedback->id,
        ]);
    }
}
