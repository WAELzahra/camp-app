<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderPaymentPreference extends Model
{
    protected $fillable = ['user_id', 'accepts_deposits', 'deposit_percentage'];

    protected $casts = [
        'accepts_deposits'   => 'boolean',
        'deposit_percentage' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Get or create preferences for a provider. */
    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['accepts_deposits' => false, 'deposit_percentage' => 30]
        );
    }
}
