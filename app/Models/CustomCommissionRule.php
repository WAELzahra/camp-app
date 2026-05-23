<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomCommissionRule extends Model
{
    protected $fillable = [
        'name',
        'description',
        'commission_rate',
        'is_active',
    ];

    protected $casts = [
        'commission_rate' => 'float',
        'is_active'       => 'boolean',
    ];

    /**
     * Users to whom this custom commission rule applies.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'custom_commission_rule_users')
                    ->withTimestamps();
    }
}
