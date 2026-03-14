<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected array $event,
        protected array $sender,
        protected string $message = ''
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Override the type stored in the DB.
     * Laravel default stores the full class name, but our notifications
     * table has an enum that only accepts specific string values.
     */
    public function databaseType(object $notifiable): string
    {
        return 'event_invitation';
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'       => "You're invited to {$this->event['title']}",
            'content'     => $this->message ?: "You have been invited to join \"{$this->event['title']}\".",
            'type'        => 'event_invitation',
            'priority'    => 'medium',
            'action_url'  => "/events/{$this->event['id']}",
            'action_text' => 'View Event',
            'event_id'    => $this->event['id'],
            'event_title' => $this->event['title'],
            'sender_id'   => $this->sender['id'],
            'sender_name' => $this->sender['name'],
        ];
    }
}