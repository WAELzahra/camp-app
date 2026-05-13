<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'user_id',
        'montant',
        'status',
        'methode',
        'details_paiement',
        'admin_note',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'details_paiement' => 'array',
        'processed_at'     => 'datetime',
        'montant'          => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
