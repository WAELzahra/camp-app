<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageReadReceipt extends Model
{
    use HasFactory;

    protected $table = 'message_read_receipts';

    protected $fillable = [
        'message_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the message that was read
     */
    public function message()
    {
        return $this->belongsTo(ChatGroupMessage::class, 'message_id');
    }

    /**
     * Get the user who read the message
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}