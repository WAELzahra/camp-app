<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatGroup extends Model
{
    use HasFactory;

    protected $fillable = ['group_id', 'name', 'invitation_token'];
    protected $casts = [
    'is_archived' => 'boolean',
];

    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_group_users');
    }

    public function messages()
    {
        return $this->hasMany(ChatGroupMessage::class);
    }

    public function campingGroup()
    {
        return $this->belongsTo(User::class, 'group_id');
    }
}

