<?php
// app/Events/GroupMessageReactionUpdated.php

namespace App\Events;

use App\Models\ChatGroupMessage;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;

class GroupMessageReactionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $user;
    public $reaction;
    public $action;
    public $reactions_grouped;

    public function __construct(ChatGroupMessage $message, User $user, string $reaction, string $action, array $reactions_grouped)
    {
        $this->message = $message;
        $this->user = $user;
        $this->reaction = $reaction;
        $this->action = $action;
        $this->reactions_grouped = $reactions_grouped;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('group.' . $this->message->chat_group_id);
    }

    public function broadcastAs()
    {
        return 'reaction.updated';
    }

    public function broadcastWith()
    {
        return [
            'message_id' => $this->message->id,
            'user' => [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
            ],
            'reaction' => $this->reaction,
            'action' => $this->action,
            'reactions_grouped' => $this->reactions_grouped,
            'timestamp' => now()->toISOString(),
        ];
    }
}