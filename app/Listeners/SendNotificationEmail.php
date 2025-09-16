<?php

namespace App\Listeners;

use App\Events\NotificationCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmail
{
    public function handle(NotificationCreated $event)
    {
        $message = $event->message;

        // Only send emails if sender is admin
        if (auth()->user() && auth()->user()->role === 'admin') {
            $users = User::all(); // all users in platform

            foreach ($users as $user) {
                Mail::to($user->email)->queue(
                    new \App\Mail\NotificationMail($message, $user)
                );
            }
        }
    }
}

