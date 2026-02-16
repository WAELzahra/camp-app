<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatGroupMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chat_group_messages';

    protected $fillable = [
        'chat_group_id',
        'sender_id',
        'reply_to_id',
        'message',
        'attachments',
        'mentions',
        'type',
        'is_edited',
        'is_pinned',
        'is_system_message',
        'sent_at',
        'edited_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'mentions' => 'array',
        'is_edited' => 'boolean',
        'is_pinned' => 'boolean',
        'is_system_message' => 'boolean',
        'sent_at' => 'datetime',
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the chat group this message belongs to
     */
    public function chatGroup()
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    /**
     * Get the sender of this message
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
        return $this->belongsTo(ChatGroupMessage::class, 'reply_to_id');
    }

    /**
     * Get replies to this message
     */
    public function replies()
    {
        return $this->hasMany(ChatGroupMessage::class, 'reply_to_id');
    }

    /**
     * Get reactions to this message
     */
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    /**
     * Get read receipts for this message
     */
    public function readReceipts()
    {
        return $this->hasMany(MessageReadReceipt::class, 'message_id');
    }

    /**
     * Get users mentioned in this message
     */
    public function mentionedUsers()
    {
        if (!$this->mentions) {
            return collect();
        }
        
        return User::whereIn('id', $this->mentions)->get();
    }

    /**
     * Check if message has attachments
     */
    public function hasAttachments()
    {
        return !empty($this->attachments);
    }

    /**
     * Check if message mentions a specific user
     */
    public function mentionsUser($userId)
    {
        return $this->mentions && in_array($userId, $this->mentions);
    }

    /**
     * Get reactions grouped by type
     */
    public function getReactionsGroupedAttribute()
    {
        return $this->reactions
            ->groupBy('reaction')
            ->map(function ($reactions) {
                return [
                    'count' => $reactions->count(),
                    'users' => $reactions->pluck('user_id'),
                ];
            });
    }

    /**
     * Scope messages from a specific sender
     */
    public function scopeFromSender($query, $senderId)
    {
        return $query->where('sender_id', $senderId);
    }

    /**
     * Scope pinned messages
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope system messages
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system_message', true);
    }

    /**
     * Scope user messages (non-system)
     */
    public function scopeUserMessages($query)
    {
        return $query->where('is_system_message', false);
    }

    /**
     * Scope messages of a specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}