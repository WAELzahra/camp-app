<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chat_groups';

    protected $fillable = [
        'group_user_id',
        'name',
        'description',
        'avatar',
        'invitation_token',
        'invitation_expires_at',
        'is_private',
        'is_archived',
        'is_active',
        'type',
        'max_members',
        'members_count',
        'messages_count',
        'last_message_at',
        'last_activity_at',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_archived' => 'boolean',
        'is_active' => 'boolean',
        'invitation_expires_at' => 'datetime',
        'last_message_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the group user (creator) who owns this chat group
     */
    public function groupUser()
    {
        return $this->belongsTo(User::class, 'group_user_id');
    }

    /**
     * Get all users in this chat group
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_group_users')
                    ->withPivot(['role', 'status', 'joined_at', 'left_at', 'muted_until', 'last_read_message_id'])
                    ->withTimestamps();
    }

    /**
     * Get the chat group users pivot records
     */
    public function members()
    {
        return $this->hasMany(ChatGroupUser::class, 'chat_group_id');
    }

    /**
     * Get active members
     */
    public function activeMembers()
    {
        return $this->members()->where('status', 'active');
    }

    /**
     * Get messages in this group
     */
    public function messages()
    {
        return $this->hasMany(ChatGroupMessage::class, 'chat_group_id');
    }

    /**
     * Get pinned messages
     */
    public function pinnedMessages()
    {
        return $this->hasMany(ChatGroupMessage::class, 'chat_group_id')
                    ->where('is_pinned', true);
    }

    /**
     * Get typing statuses
     */
    public function typingStatuses()
    {
        return $this->hasMany(ChatGroupTypingStatus::class, 'chat_group_id');
    }

    /**
     * Get users currently typing
     */
    public function currentlyTyping()
    {
        return $this->typingStatuses()
                    ->where('is_typing', true)
                    ->where('updated_at', '>=', now()->subSeconds(10));
    }

    /**
     * Check if user is member of this group
     */
    public function hasMember($userId)
    {
        return $this->members()
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->exists();
    }

    /**
     * Check if user is admin of this group
     */
    public function isAdmin($userId)
    {
        return $this->members()
                    ->where('user_id', $userId)
                    ->where('role', 'admin')
                    ->where('status', 'active')
                    ->exists();
    }

    /**
     * Get member by user ID
     */
    public function getMember($userId)
    {
        return $this->members()->where('user_id', $userId)->first();
    }

    /**
     * Get invitation link
     */
    public function getInvitationLinkAttribute()
    {
        return $this->invitation_token 
            ? url("/api/group-chat/join/{$this->invitation_token}")
            : null;
    }

    /**
     * Check if invitation is valid
     */
    public function isInvitationValid()
    {
        return $this->invitation_token 
            && (!$this->invitation_expires_at || $this->invitation_expires_at > now());
    }

    /**
     * Scope active groups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope not archived
     */
    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope groups created by a specific group user
     */
    public function scopeCreatedBy($query, $groupId)
    {
        return $query->where('group_user_id', $groupId);
    }
}