<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'category',
        'amount_gross',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'amount_gross'      => 'float',
        'commission_rate'   => 'float',
        'commission_amount' => 'float',
        'net_amount'        => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function logCredit(
        int $userId,
        string $category,
        float $amountGross,
        float $commissionRate,
        float $commissionAmount,
        float $netAmount,
        string $referenceType,
        ?int $referenceId = null,
        string $description = ''
    ): self {
        return self::create([
            'user_id'           => $userId,
            'type'              => 'credit',
            'category'          => $category,
            'amount_gross'      => $amountGross,
            'commission_rate'   => $commissionRate,
            'commission_amount' => $commissionAmount,
            'net_amount'        => $netAmount,
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'description'       => $description,
        ]);
    }

    public static function logDebit(
        int $userId,
        string $category,
        float $amount,
        string $referenceType,
        ?int $referenceId = null,
        string $description = ''
    ): self {
        return self::create([
            'user_id'           => $userId,
            'type'              => 'debit',
            'category'          => $category,
            'amount_gross'      => $amount,
            'commission_rate'   => 0,
            'commission_amount' => 0,
            'net_amount'        => $amount,
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'description'       => $description,
        ]);
    }
}
