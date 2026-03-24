<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int      $commentId,
        public readonly int|null $parentId,
        public readonly int      $likesCount,
        public readonly int      $annonceId,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('annonce.' . $this->annonceId)];
    }

    public function broadcastAs(): string
    {
        return 'comment.liked';
    }

    public function broadcastWith(): array
    {
        return [
            'comment_id'  => $this->commentId,
            'parent_id'   => $this->parentId,
            'likes_count' => $this->likesCount,
        ];
    }
}