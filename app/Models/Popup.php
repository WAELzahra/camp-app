<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Popup extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'content', 'type', 'is_active',
        'popup_kind', 'target_roles', 'icon', 'cta_label', 'cta_url',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'target_roles' => 'array',
    ];

    public function userStates()
    {
        return $this->hasMany(UserPopupState::class);
    }
}
