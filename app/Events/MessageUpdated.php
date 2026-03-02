<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $action;

    public function __construct(Message $message, string $action = 'edited')
    {
        $this->message = $message;
        $this->action = $action;
    }

    /**
     * ✅ FIXED: Must be PrivateChannel to match the frontend's
     * private-conversation.{id} subscription in WebSocketContext.
     * Using public Channel() meant the frontend never received the event.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id'              => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id'       => $this->message->sender_id,
                'content'         => $this->message->content,
                'edited_at'       => $this->message->edited_at,
                'type'            => $this->message->type,
                'created_at'      => $this->message->created_at,
            ],
            'action' => $this->action,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }
}