<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerType extends Model
{
    protected $fillable = [
        'code',
        'label',
        'icon',
        'requires_platform_account',
    ];

    protected $casts = [
        'requires_platform_account' => 'boolean',
    ];

    public function partners()
    {
        return $this->hasMany(Partner::class);
    }
}
