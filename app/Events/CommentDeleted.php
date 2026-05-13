<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int      $commentId,
        public readonly int|null $parentId,
        public readonly int      $annonceId,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('annonce.' . $this->annonceId)];
    }

    public function broadcastAs(): string
    {
        return 'comment.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'comment_id' => $this->commentId,
            'parent_id'  => $this->parentId,
        ];
    }
}