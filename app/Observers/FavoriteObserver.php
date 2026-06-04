<?php

namespace App\Observers;

use App\Models\Favorite;
use App\Services\AI\BehavioralProfileService;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates the behavioral profile cache when a zone or centre is favorited.
 * Zone favorites feed the inferred_terrain_preference signal directly.
 */
class FavoriteObserver
{
    public function __construct(
        private readonly BehavioralProfileService $behavioralProfileService,
    ) {}

    public function created(Favorite $favorite): void
    {
        if (! in_array($favorite->favoritable_type, ['zone', 'centre'], true)) {
            return;
        }

        $userId = $favorite->user_id;
        if (! $userId) {
            return;
        }

        $this->behavioralProfileService->invalidate($userId);

        Log::info('behavioral_profile_invalidated', [
            'trigger'          => 'favorite_created',
            'user_id'          => $userId,
            'favoritable_type' => $favorite->favoritable_type,
            'favoritable_id'   => $favorite->favoritable_id,
        ]);
    }
}
