<?php
// app/Listeners/CreateGroupChatForGroupUser.php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateGroupChatForGroupUser
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        
        // Only create group chat for group users (role_id = 2)
        if ($user->role_id === 2) {
            try {
                // Create a group conversation for this group user
                $conversation = Conversation::create([
                    'type' => 'group',
                    'name' => $user->first_name . ' ' . $user->last_name . "'s Group",
                    'created_by' => $user->id,
                    'group_id' => $user->id, // Link to the group user
                    'metadata' => json_encode([
                        'description' => 'Official group chat for ' . $user->first_name . ' ' . $user->last_name,
                        'auto_created' => true
                    ])
                ]);

                // Add the group creator as admin
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'role' => 'admin',
                    'joined_at' => now(),
                    'is_muted' => false
                ]);

                Log::info('Auto-created group chat for group user: ' . $user->email);
                
            } catch (\Exception $e) {
                Log::error('Failed to create group chat for user: ' . $user->email . ' - ' . $e->getMessage());
            }
        }
    }
}