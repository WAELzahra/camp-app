<?php
// app/Models/Message.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'messages';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'type',
        'reply_to_id',
        'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    /**
     * Get the conversation this message belongs to
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the message this is replying to
     */
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    /**
     * Get replies to this message
     */
    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    /**
     * Get statuses for this message
     */
    public function statuses()
    {
        return $this->hasMany(MessageStatus::class);
    }

    /**
     * Get attachments for this message
     */
    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * Get reactions for this message
     */
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Check if message has been read by a specific user
     */
    public function isReadBy($userId)
    {
        return $this->statuses()
            ->where('user_id', $userId)
            ->whereNotNull('read_at')
            ->exists();
    }

    /**
     * Check if message has been delivered to a specific user
     */
    public function isDeliveredTo($userId)
    {
        return $this->statuses()
            ->where('user_id', $userId)
            ->whereNotNull('delivered_at')
            ->exists();
    }

    /**
     * Mark message as delivered for user
     */
    public function markAsDeliveredFor($userId)
    {
        return $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            ['delivered_at' => now()]
        );
    }

    /**
     * Mark message as read for user
     */
    public function markAsReadFor($userId)
    {
        return $this->statuses()->updateOrCreate(
            ['user_id' => $userId],
            [
                'delivered_at' => now(),
                'read_at' => now()
            ]
        );
    }

    /**
     * Get read count
     */
    public function getReadCountAttribute()
    {
        return $this->statuses()->whereNotNull('read_at')->count();
    }

    /**
     * Get delivered count
     */
    public function getDeliveredCountAttribute()
    {
        return $this->statuses()->whereNotNull('delivered_at')->count();
    }

    /**
     * Scope for messages in a conversation
     */
    public function scopeInConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Scope for messages sent by user
     */
    public function scopeSentBy($query, $userId)
    {
        return $query->where('sender_id', $userId);
    }

    /**
     * Scope for text messages
     */
    public function scopeText($query)
    {
        return $query->where('type', 'text');
    }

    /**
     * Scope for unread messages for a user
     */
    public function scopeUnreadForUser($query, $userId)
    {
        return $query->whereDoesntHave('statuses', function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereNotNull('read_at');
        });
    }
}