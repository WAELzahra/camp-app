<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Message;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $message;

    public function __construct(User $user, Message $message)
    {
        $this->user = $user;
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        $channels = [
            // ✅ Existing: conversation channel (for open chat windows)
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];

        // ✅ NEW: also broadcast on each RECIPIENT's private user channel
        // This lets the navbar bell update in real-time even when the chat isn't open
        $recipientIds = $this->message->conversation
            ->participants()
            ->where('user_id', '!=', $this->user->id)  // exclude sender
            ->whereNull('left_at')
            ->pluck('user_id');

        foreach ($recipientIds as $recipientId) {
            $channels[] = new PrivateChannel('user.' . $recipientId);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'message' => array_merge($this->message->toArray(), [
                'sender' => $this->user->only('id', 'first_name', 'last_name', 'avatar'),
            ]),
            'conversation_id'   => $this->message->conversation_id,
            // ✅ Let the frontend know whether this is a DM or group message
            // so it can route the bell notification click to the right tab
            'conversation_type' => $this->message->conversation->type,
            'conversation_name' => $this->message->conversation->name,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}