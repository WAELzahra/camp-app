<?php

namespace App\Models;

use App\Models\User;

use Illuminate\Database\Eloquent\Model;

class ChatGroupUser extends Model
{
    protected $fillable = ['chat_group_id', 'user_id'];

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

        public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

    public function chatGroup()
    {
        return $this->belongsTo(ChatGroup::class);
    }



}
