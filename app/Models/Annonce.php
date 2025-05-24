<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annonce extends Model
{
    use HasFactory;
    protected $fillable = [
        "user_id",
        "description",
        "status"
    ];

    public function photo(){
        return $this->hasMany(Photos::class);
    }

}
