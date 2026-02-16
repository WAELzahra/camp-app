<?php

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

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the message this reaction belongs to
     */
    public function message()
    {
        return $this->belongsTo(ChatGroupMessage::class, 'message_id');
    }

    /**
     * Get the user who reacted
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get emoji as HTML entity (for display)
     */
    public function getEmojiHtmlAttribute()
    {
        $emojis = [
            '👍' => '&#128077;',
            '❤️' => '&#10084;&#65039;',
            '😂' => '&#128514;',
            '😮' => '&#128562;',
            '😢' => '&#128546;',
            '😡' => '&#128545;',
            '🎉' => '&#127881;',
            '👏' => '&#128079;',
            '🔥' => '&#128293;',
            '✅' => '&#9989;',
        ];
        
        return $emojis[$this->reaction] ?? $this->reaction;
    }
}