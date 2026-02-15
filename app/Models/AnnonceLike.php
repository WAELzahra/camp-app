<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnonceLike extends Model
{
    use HasFactory;

    protected $table = 'annonce_likes';

    protected $fillable = [
        'user_id',
        'annonce_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function annonce()
    {
        return $this->belongsTo(Annonce::class);
    }
}