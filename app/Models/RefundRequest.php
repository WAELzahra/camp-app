<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'reservation_event_id',
        'reservation_centre_id',
        'payment_id',
        'montant_rembourse',
        'net_amount',
        'commission_amount',
        'commission_rate',
        'payment_channel',
        'reason',
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

    public function reservationCentre()
    {
        return $this->belongsTo(\App\Models\Reservations_centre::class, 'reservation_centre_id');
    }
}
