<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Photos extends Model
{
    use HasFactory;

    protected $fillable = [
        "path_to_img",
        "user_id",
        "annonce_id",
        "materielle_id"
    ];
    
    public function annonce(){
        return $this->belongsTo(Annonce::class);
    }

    public function profile(){
        return $this->belongsTo(User::class);
    }

    public function event(){
        return $this->belongsTo(Events::class);
    }
}
