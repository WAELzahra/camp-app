<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;
use App\Models\User;

class MessageReactionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $user;
    public $reaction;
    public $action;
    public $reactionsGrouped;

    public function __construct(Message $message, User $user, string $reaction, string $action, array $reactionsGrouped)
    {
        $this->message = $message;
        $this->user = $user;
        $this->reaction = $reaction;
        $this->action = $action;
        $this->reactionsGrouped = $reactionsGrouped;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'        => $this->message->id,
            'reaction'          => $this->reaction,
            'action'            => $this->action,
            'reactions_grouped' => $this->reactionsGrouped,
            'user'              => $this->user->only('id', 'first_name', 'last_name', 'avatar'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction.updated';
    }
}