<?php
// app/Events/GroupMessageSent.php

namespace App\Events;

use App\Models\ChatGroupMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;

class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(ChatGroupMessage $message)
    {
        // Load relationships for the message
        $message->load('sender:id,first_name,last_name,avatar');
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('group.' . $this->message->chat_group_id);
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }

    public function broadcastWith()
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'chat_group_id' => $this->message->chat_group_id,
                'sender_id' => $this->message->sender_id,
                'message' => $this->message->message,
                'type' => $this->message->type,
                'attachments' => $this->message->attachments,
                'created_at' => $this->message->created_at,
                'sent_at' => $this->message->sent_at,
                'sender' => [
                    'id' => $this->message->sender->id,
                    'first_name' => $this->message->sender->first_name,
                    'last_name' => $this->message->sender->last_name,
                    'avatar' => $this->message->sender->avatar,
                ],
                'reactions_grouped' => [],
            ]
        ];
    }
}