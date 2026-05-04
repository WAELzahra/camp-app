<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationPolicyTier extends Model
{
    protected $fillable = ['policy_id', 'hours_before', 'fee_percentage', 'label'];

    protected $casts = [
        'hours_before'   => 'integer',
        'fee_percentage' => 'float',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class, 'policy_id');
    }
}
