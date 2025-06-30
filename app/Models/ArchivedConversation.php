<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedConversation extends Model
{
    protected $fillable = ['user_id', 'receiver_id', 'event_id'];
}
