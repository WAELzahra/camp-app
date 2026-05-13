<?php
// app/Models/Conversation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'conversations';

    protected $fillable = [
        'type',
        'name',
        'avatar',
        'created_by',
        'group_id', 
        'last_message_at',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the user who created the conversation
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the group associated with this conversation (if any)
     * This links to users with role_id = 2
     */
    public function group()
    {
        return $this->belongsTo(User::class, 'group_id');
    }

    /**
     * Get all participants in this conversation
     */
    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get all users in this conversation
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('role', 'last_read_at', 'joined_at', 'left_at', 'is_muted')
            ->withTimestamps();
    }

    /**
     * Get all messages in this conversation
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the latest message in this conversation
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Check if user is participant
     */
    public function hasParticipant($userId)
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Get participant for a specific user
     */
    public function getParticipant($userId)
    {
        return $this->participants()->where('user_id', $userId)->first();
    }

    /**
     * Mark conversation as read for user
     */
    public function markAsReadForUser($userId)
    {
        return $this->participants()
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCountForUser($userId)
    {
        $participant = $this->participants()
            ->where('user_id', $userId)
            ->first();

        if (!$participant || !$participant->last_read_at) {
            return 0;
        }

        return $this->messages()
            ->where('created_at', '>', $participant->last_read_at)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Check if conversation belongs to a group user
     */
    public function belongsToGroup()
    {
        return !is_null($this->group_id);
    }

    /**
     * Get the group owner details
     */
    public function groupOwner()
    {
        return $this->belongsTo(User::class, 'group_id');
    }

    /**
     * Scope for direct message conversations
     */
    public function scopeDirect($query)
    {
        return $query->where('type', 'direct');
    }

    /**
     * Scope for group conversations
     */
    public function scopeGroup($query)
    {
        return $query->where('type', 'group');
    }

    /**
     * Scope for conversations linked to a specific group
     */
    public function scopeForGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Scope for conversations with unread messages
     */
    public function scopeWithUnread($query, $userId)
    {
        return $query->whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where(function ($subQ) {
                  $subQ->whereNull('last_read_at')
                       ->orWhereRaw('last_read_at < (select max(created_at) from messages where messages.conversation_id = conversations.id)');
              });
        });
    }
}