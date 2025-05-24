<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Messages extends Model
{
    use HasFactory;
    protected $fillable = [
        "sender_id",
        "target_id",
        "type",
        "contenu",
        "is_read",
        "degree_urgence"
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function user_target(){
        return $this->hasOne(User::class);
    }
}
