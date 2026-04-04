<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPopupState extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'popup_id', 'is_dismissed'];

    protected $casts = [
        'is_dismissed' => 'boolean',
    ];

    public function popup()
    {
        return $this->belongsTo(Popup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
