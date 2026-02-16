<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroupTypingStatus extends Model
{
    use HasFactory;

    protected $table = 'chat_group_typing_status';

    protected $fillable = [
        'chat_group_id',
        'user_id',
        'is_typing',
    ];

    protected $casts = [
        'is_typing' => 'boolean',
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
     * Check if typing status is still valid (within last 10 seconds)
     */
    public function isValid()
    {
        return $this->is_typing && $this->updated_at >= now()->subSeconds(10);
    }

    /**
     * Scope valid typing statuses
     */
    public function scopeValid($query)
    {
        return $query->where('is_typing', true)
                     ->where('updated_at', '>=', now()->subSeconds(10));
    }
}