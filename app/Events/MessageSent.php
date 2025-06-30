<?php

// namespace App\Events;

// use Illuminate\Broadcasting\Channel;
// use Illuminate\Queue\SerializesModels;
// use Illuminate\Broadcasting\InteractsWithSockets;
// use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
// use App\Models\User;

// class MessageSent implements ShouldBroadcast
// {
//     use InteractsWithSockets, SerializesModels;

//     public $user;
//     public $message;

//     public function __construct(User $user, $message)
//     {
//         $this->user = $user;
//         $this->message = $message;
//     }

//     public function broadcastOn()
//     {
//         return new Channel('chat');
//     }

//     public function broadcastWith()
//     {
//         return [
//             'user' => $this->user->name,
//             'message' => $this->message,
//         ];
//     }
// }


namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $message;

    public function __construct(User $user, $message)
    {
        $this->user = $user;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat');
    }

    public function broadcastWith()
    {
        return [
            'user' => $this->user->only('id', 'name', 'email'), // adapte selon ton User model
            'message' => $this->message,
        ];
    }
}
