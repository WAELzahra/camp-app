<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroupUser extends Model
{
    use HasFactory;

    protected $table = 'chat_group_users';

    protected $fillable = [
        'chat_group_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'left_at',
        'muted_until',
        'notifications_enabled',
        'notification_mode',
        'last_read_message_id',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'muted_until' => 'datetime',
        'notifications_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the chat group
     */
    public function chatGroup()
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the last read message
     */
    public function lastReadMessage()
    {
        return $this->belongsTo(ChatGroupMessage::class, 'last_read_message_id');
    }

    /**
     * Check if member is muted
     */
    public function isMuted()
    {
        return $this->muted_until && $this->muted_until > now();
    }

    /**
     * Check if member is admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if member is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Scope active members
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope admins
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Scope members (non-admins)
     */
    public function scopeMembers($query)
    {
        return $query->where('role', 'member');
    }

    /**
     * Scope muted members
     */
    public function scopeMuted($query)
    {
        return $query->where('status', 'muted')
                     ->where('muted_until', '>', now());
    }
}