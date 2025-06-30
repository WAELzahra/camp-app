<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FollowersGroupe extends Model
{
    protected $fillable = ['user_id', 'groupe_id'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function groupe()
{
    return $this->belongsTo(ProfileGroupe::class, 'groupe_id');
}

}

