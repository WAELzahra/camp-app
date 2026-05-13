<?php
// app/Models/NotificationLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $table = 'notification_logs';

    protected $fillable = [
        'notification_id',
        'user_id',
        'channel',
        'status',
        'error_message',
        'opened_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
    ];

    /**
     * Get the notification
     */
    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark as opened
     */
    public function markAsOpened()
    {
        $this->update([
            'status' => 'opened',
            'opened_at' => now()
        ]);
    }

    /**
     * Scope by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for failed deliveries
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}