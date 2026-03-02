<?php
// app/Notifications/CustomNotification.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;
    protected $channels;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data, array $channels = ['in_app'])
    {
        $this->data = $data;
        $this->channels = $channels;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->data['title'],
            'content' => $this->data['content'],
            'type' => $this->data['type'],
            'priority' => $this->data['priority'],
            'action_url' => $this->data['action_url'] ?? null,
            'action_text' => $this->data['action_text'] ?? null,
            'image' => $this->data['image'] ?? null,
            'sender_id' => $this->data['sender_id'] ?? null,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->data['title'])
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line($this->data['content']);

        if (isset($this->data['action_url']) && isset($this->data['action_text'])) {
            $mail->action($this->data['action_text'], $this->data['action_url']);
        }

        $mail->line('Thank you for using our application!');

        return $mail;
    }
}