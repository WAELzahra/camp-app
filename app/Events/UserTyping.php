<?php

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcast
{
    use SerializesModels;

    public $senderId;
    public $receiverId;
    public $eventId;

    public function __construct($senderId, $receiverId, $eventId)
    {
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
        $this->eventId = $eventId;
    }

    public function broadcastOn()
    {
        return new Channel('chat.' . $this->receiverId . '.' . $this->eventId);
    }

    public function broadcastAs()
    {
        return 'user.typing';
    }
}

