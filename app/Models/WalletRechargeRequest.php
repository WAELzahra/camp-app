<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletRechargeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'method',
        'payment_reference',
        'transfer_reference',
        'credited_amount',
        'status',
        'submitted_at',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'amount'          => 'float',
        'credited_amount' => 'float',
        'submitted_at'    => 'datetime',
        'confirmed_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
