<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * CustomNotification
 *
 * Stores a notification in the database (in_app channel).
 * Real-time delivery is handled separately by firing
 * NewNotificationCreated after the record is persisted.
 */
class CustomNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected array $data,
        protected array $channels = ['in_app']
    ) {}

    /* ── Laravel notification channels ───────────────────────────────────── */

    public function via(object $notifiable): array
    {
        return in_array('in_app', $this->channels) ? ['database'] : [];
    }

    /**
     * Override the "type" stored in the notifications table.
     * Without this, Laravel stores the full class path.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->data['type'] ?? 'system_alert';
    }

    /* ── Database payload ────────────────────────────────────────────────── */

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'       => $this->data['title'],
            'content'     => $this->data['content'],
            'type'        => $this->data['type']        ?? 'system_alert',
            'priority'    => $this->data['priority']    ?? 'low',
            'action_url'  => $this->data['action_url']  ?? null,
            'action_text' => $this->data['action_text'] ?? null,
            'image'       => $this->data['image']       ?? null,
            'sender_id'   => $this->data['sender_id']   ?? null,
        ];
    }
}
