<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletRechargeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'payment_reference',
        'status',
        'submitted_at',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'amount'       => 'float',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
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
