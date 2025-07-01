<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatGroupMessage extends Model
{
    protected $fillable = ['chat_group_id', 'sender_id', 'message'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function group()
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }
}
