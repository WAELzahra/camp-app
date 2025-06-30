<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\ChatMessage;

class PrivateMessageSent implements ShouldBroadcast
{
    public $chatMessage;

    public function __construct(ChatMessage $chatMessage)
    {
        $this->chatMessage = $chatMessage;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat.' . $this->chatMessage->receiver_id);
    }
}
