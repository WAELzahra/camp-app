<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentPinned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $comment,
        public readonly int   $annonceId,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('annonce.' . $this->annonceId)];
    }

    public function broadcastAs(): string
    {
        return 'comment.pinned';
    }

    public function broadcastWith(): array
    {
        return ['comment' => $this->comment];
    }
}