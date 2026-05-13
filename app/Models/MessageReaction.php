<?php
// app/Models/MessageReaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageReaction extends Model
{
    use HasFactory;

    protected $table = 'message_reactions';

    protected $fillable = [
        'message_id',
        'user_id',
        'reaction',
    ];

    /**
     * Get the message
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user who reacted
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for a specific reaction
     */
    public function scopeWithReaction($query, $reaction)
    {
        return $query->where('reaction', $reaction);
    }
}