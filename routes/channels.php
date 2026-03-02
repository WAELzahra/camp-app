<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\Conversation::find($conversationId);

    if (!$conversation || !$conversation->hasParticipant($user->id)) {
        return false;
    }

    return [
        'id'         => $user->id,
        'first_name' => $user->first_name,
        'last_name'  => $user->last_name,
        'avatar'     => $user->avatar,
        'is_online'  => $user->isOnline(),
        'last_seen'  => $user->last_login_at,
    ];
});

// ✅ NEW: each user has their own private channel for global real-time events
// (new messages from any conversation, system alerts, etc.)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    // Only the authenticated user can join their own channel
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    if (!$user) return false;
    return true;
});