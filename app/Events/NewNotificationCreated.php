<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a notification to a specific user in real time.
 *
 * Fires on the user's existing private channel `user.{id}` — the same
 * channel the WebSocketContext already listens to for message events.
 * The frontend handler subscribes to `.notification.created`.
 */
class NewNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $userId,
        public readonly string $notificationId,
        public readonly string $title,
        public readonly string $content,
        public readonly string $type,
        public readonly string $priority,
        public readonly ?string $actionUrl  = null,
        public readonly ?string $actionText = null,
        public readonly ?int    $senderId   = null,
        public readonly string  $createdAt  = '',
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->notificationId,
            'title'       => $this->title,
            'content'     => $this->content,
            'type'        => $this->type,
            'priority'    => $this->priority,
            'action_url'  => $this->actionUrl,
            'action_text' => $this->actionText,
            'sender_id'   => $this->senderId,
            'read_at'     => null,
            'created_at'  => $this->createdAt ?: now()->toISOString(),
        ];
    }
}
