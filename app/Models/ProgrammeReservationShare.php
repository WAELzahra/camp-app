<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeReservationShare extends Model
{
    protected $fillable = [
        'programme_reservation_id',
        'partner_id',
        'programme_step_partner_id',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'partner_credited',
        'released_at',
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'commission_rate' => 'float',
        'commission_amount' => 'float',
        'net_amount' => 'float',
        'partner_credited' => 'boolean',
        'released_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(ProgrammeReservation::class, 'programme_reservation_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function stepPartner()
    {
        return $this->belongsTo(ProgrammeStepPartner::class, 'programme_step_partner_id');
    }
}
