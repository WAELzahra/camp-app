<?php
// app/Models/MessageStatus.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageStatus extends Model
{
    use HasFactory;

    protected $table = 'message_statuses';

    protected $fillable = [
        'message_id',
        'user_id',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Get the message
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if message is read
     */
    public function isRead()
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if message is delivered
     */
    public function isDelivered()
    {
        return !is_null($this->delivered_at);
    }

    /**
     * Scope for read statuses
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope for delivered statuses
     */
    public function scopeDelivered($query)
    {
        return $query->whereNotNull('delivered_at');
    }
}