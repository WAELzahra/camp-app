<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'reservation_event_id',
        'payment_id',
        'montant_rembourse',
        'status',
    ];

    public function payment()
    {
        return $this->belongsTo(Payments::class, 'payment_id');
    }

    public function reservationEvent()
    {
        return $this->belongsTo(Reservations_events::class, 'reservation_event_id');
    }
}
