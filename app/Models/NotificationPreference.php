<?php
// app/Models/NotificationPreference.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if notification type is enabled for channel
     */
    public static function isEnabled($userId, $type, $channel)
    {
        $preference = self::where('user_id', $userId)
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();
            
        return $preference ? $preference->enabled : true; // Default to true
    }

    /**
     * Scope for enabled preferences
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}