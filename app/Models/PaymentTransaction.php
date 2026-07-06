<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'reservation_id', 'reservation_type', 'user_id', 'gateway', 'amount',
        'currency', 'status', 'gateway_reference', 'gateway_response',
        'payment_type', 'original_transaction_id', 'processed_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'processed_at'     => 'datetime',
    ];

    protected $attributes = [
        'currency' => 'TND',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function originalTransaction()
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }

    public function refunds()
    {
        return $this->hasMany(self::class, 'original_transaction_id');
    }

    /**
     * Task A-04 §4D — refunds are NEW records linked to the original;
     * the original transaction is never modified.
     */
    public function createRefund(float $amount, ?array $gatewayResponse = null): self
    {
        return self::create([
            'reservation_id'          => $this->reservation_id,
            'reservation_type'        => $this->reservation_type,
            'user_id'                 => $this->user_id,
            'gateway'                 => $this->gateway,
            'amount'                  => $amount,
            'currency'                => $this->currency ?? 'TND',
            'status'                  => 'completed',
            'gateway_response'        => $gatewayResponse,
            'payment_type'            => 'refund',
            'original_transaction_id' => $this->id,
            'processed_at'            => now(),
        ]);
    }
}
