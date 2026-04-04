<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Popup extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'content', 'type', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function userStates()
    {
        return $this->hasMany(UserPopupState::class);
    }
}
