<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signales extends Model
{
    use HasFactory;
    protected $fillable = [
        "user_id",
        "target_id",
        "type",
        "contenu"
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
    
    public function target_user(){
        return $this->belongsTo(User::class);
    }
}
