<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\Conversation::find($conversationId);

    if (!$conversation || !$conversation->hasParticipant($user->id)) {
        return false;
    }

    return [
        // uuid is the stable identifier the frontend now uses everywhere
        'id'         => $user->uuid,
        'uuid'       => $user->uuid,
        'first_name' => $user->first_name,
        'last_name'  => $user->last_name,
        'avatar'     => $user->avatar,
        'is_online'  => $user->isOnline(),
        'last_seen'  => $user->last_login_at,
    ];
});

// Private channel for each user — keyed by UUID so the frontend never needs the numeric id
Broadcast::channel('user.{userUuid}', function ($user, $userUuid) {
    return $user->uuid === $userUuid;
});

Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    if (!$user) return false;
    return true;
});

// Admin-only alerts channel (new reports, system alerts)
Broadcast::channel('admin-alerts', function ($user) {
    return $user && $user->role_id === 6;
});
