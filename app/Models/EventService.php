<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventService extends Model
{
    use HasFactory;

    protected $table = 'event_services';

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'pricing_unit',
        'max_quantity',
        'is_active',
    ];

    protected $casts = [
        'price'        => 'float',
        'max_quantity' => 'integer',
        'is_active'    => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function reservationServices()
    {
        return $this->hasMany(EventReservationService::class, 'event_service_id');
    }
}
