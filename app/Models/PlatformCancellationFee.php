<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformCancellationFee extends Model
{
    protected $fillable = ['actor_type', 'fee_percentage', 'is_active'];

    protected $casts = [
        'fee_percentage' => 'float',
        'is_active'      => 'boolean',
    ];

    public static function forActor(string $actorType): ?self
    {
        return static::where('actor_type', $actorType)->first();
    }

    /**
     * Returns the active fee amount for the given actor type, or 0 if inactive.
     */
    public static function feeAmount(string $actorType, float $gross): float
    {
        $record = static::where('actor_type', $actorType)->where('is_active', true)->first();
        if (!$record || $record->fee_percentage <= 0) return 0.0;
        return round($gross * $record->fee_percentage / 100, 2);
    }
}
