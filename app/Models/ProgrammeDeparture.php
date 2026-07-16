<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeDeparture extends Model
{
    protected $fillable = [
        'programme_id',
        'start_date',
        'end_date',
        'capacity_max',
        'capacity_booked',
        'price_override',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price_override' => 'float',
    ];

    public function programme()
    {
        return $this->belongsTo(Programme::class);
    }

    public function reservations()
    {
        return $this->hasMany(ProgrammeReservation::class);
    }

    public function pricePerParticipant(): float
    {
        return $this->price_override !== null
            ? (float) $this->price_override
            : $this->programme->basePrice();
    }

    public function seatsRemaining(): int
    {
        return max(0, $this->capacity_max - $this->capacity_booked);
    }
}
