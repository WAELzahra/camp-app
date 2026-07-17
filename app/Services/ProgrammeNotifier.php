<?php

namespace App\Services;

use App\Events\NewNotificationCreated;
use App\Models\User;
use App\Notifications\CustomNotification;
use Illuminate\Support\Facades\Log;

/**
 * In-app + realtime notifications for the Programme reservation lifecycle,
 * mirroring the pattern already used by AdminPaymentReviewController (the
 * Event/Centre reservation flows only send Mail — this closes that gap for
 * Programme reservations specifically).
 */
class ProgrammeNotifier
{
    public static function notify(
        int $userId,
        string $title,
        string $content,
        string $type = 'status_update',
        string $priority = 'medium',
        ?string $actionUrl = null
    ): void {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $data = [
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'priority' => $priority,
            'action_url' => $actionUrl,
        ];

        try {
            $user->notify(new CustomNotification($data, ['in_app']));
        } catch (\Throwable $e) {
            Log::warning('Programme notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return;
        }

        $latest = $user->notifications()->latest()->first();
        if (!$latest) {
            return;
        }

        try {
            event(new NewNotificationCreated(
                userId: $user->id,
                notificationId: $latest->id,
                title: $title,
                content: $content,
                type: $type,
                priority: $priority,
                actionUrl: $actionUrl,
            ));
        } catch (\Throwable $e) {
            Log::warning('Programme notification broadcast failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }
}
