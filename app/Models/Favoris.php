<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favoris extends Model
{
    use HasFactory;
    protected $fillable = [
        "user_id",
        "target_id",
        "type"
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }


}
