<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventReservationService extends Model
{
    use HasFactory;

    protected $table = 'event_reservation_services';

    protected $fillable = [
        'event_reservation_id',
        'event_service_id',
        'quantity',
        'notes',
        'price_snapshot',
        'pricing_unit_snapshot',
        'subtotal',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
        'subtotal'       => 'decimal:2',
        'quantity'       => 'integer',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservations_events::class, 'event_reservation_id');
    }

    public function service()
    {
        return $this->belongsTo(EventService::class, 'event_service_id');
    }
}
