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

    public function photos(){
        return $this->hasMany(Photos::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::creating(function ($annonce) {
            if (auth()->check()) {
                $annonce->user_id = auth()->id();
            }
        });
    }
    
}
