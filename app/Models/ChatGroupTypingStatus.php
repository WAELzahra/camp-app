<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroupTypingStatus extends Model
{
    protected $fillable = ['chat_group_id', 'user_id', 'is_typing'];
    public $timestamps = false;

    public function user() {
        return $this->belongsTo(User::class);
    }
}
