<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeReservationShare extends Model
{
    protected $fillable = [
        'programme_reservation_id',
        'programme_item_id',
        'owner_user_id',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'credited',
        'released_at',
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'commission_rate' => 'float',
        'commission_amount' => 'float',
        'net_amount' => 'float',
        'credited' => 'boolean',
        'released_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(ProgrammeReservation::class, 'programme_reservation_id');
    }

    public function item()
    {
        return $this->belongsTo(ProgrammeItem::class, 'programme_item_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
