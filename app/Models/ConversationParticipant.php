<?php
// app/Models/ConversationParticipant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationParticipant extends Model
{
    use HasFactory;

    protected $table = 'conversation_participants';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_at',
        'joined_at',
        'left_at',
        'is_muted',
        'status'
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_muted' => 'boolean',
    ];

    /**
     * Get the conversation
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if participant is admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if participant is still active
     */
    public function isActive()
    {
        return is_null($this->left_at);
    }

    /**
     * Mark as read
     */
    public function markAsRead()
    {
        $this->update(['last_read_at' => now()]);
    }

    /**
     * Scope for active participants
     */
    public function scopeActive($query)
    {
        return $query->whereNull('left_at');
    }

    /**
     * Scope for admins
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }
}