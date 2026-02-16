<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatGroupUser;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Register the broadcasting auth route
// This is the key to making /broadcasting/auth available
Broadcast::routes(['middleware' => ['auth:sanctum']]); // or 'auth:api' if you use token auth

// Private group channel
Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    // Only allow active members of the group
    return ChatGroupUser::where('chat_group_id', $groupId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});

// Optional: existing user channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
