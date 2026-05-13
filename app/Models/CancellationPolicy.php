<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationPolicy extends Model
{
    protected $fillable = ['type', 'name', 'centre_id', 'is_active', 'grace_period_hours'];

    protected $casts = [
        'is_active'           => 'boolean',
        'grace_period_hours'  => 'integer',
    ];

    public function tiers(): HasMany
    {
        return $this->hasMany(CancellationPolicyTier::class, 'policy_id')
                    ->orderByDesc('hours_before');
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'centre_id');
    }
}
