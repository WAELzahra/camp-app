<?php
// app/Models/Notification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use HasFactory;

    protected $table = 'notifications';

    /**
     * Get the notifiable entity (user, group, etc.)
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Get the sender (admin or system)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope for archived notifications
     */
    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Scope by priority
     */
    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Mark as read
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => $this->freshTimestamp()])->save();
        }
    }

    /**
     * Mark as archived
     */
    public function markAsArchived()
    {
        if (is_null($this->archived_at)) {
            $this->forceFill(['archived_at' => $this->freshTimestamp()])->save();
        }
    }

    /**
     * Get notification data as object
     */
    public function getDataAttribute($value)
    {
        return json_decode($value);
    }
}