<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Events;

class ChatMessage extends Model
{
    use HasFactory;
    use SoftDeletes;

   protected $fillable = [
    'sender_id',
    'receiver_id',
    'event_id',
    'message',
    'message_type',
    'file_path',
    'is_read',
    'read_at',
];


    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}

