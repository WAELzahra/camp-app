<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminWalletTransaction extends Model
{
    protected $fillable = [
        'category', 'amount', 'reference_type', 'reference_id',
        'related_user_id', 'description',
    ];

    protected $casts = ['amount' => 'float'];

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public static function log(
        string $category,
        float  $amount,
        string $referenceType,
        ?int   $referenceId,
        string $description = '',
        ?int   $relatedUserId = null
    ): self {
        return static::create([
            'category'        => $category,
            'amount'          => $amount,
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'description'     => $description,
            'related_user_id' => $relatedUserId,
        ]);
    }
}
