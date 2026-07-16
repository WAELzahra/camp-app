<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = [
        'partner_type_id',
        'user_id',
        'name',
        'contact_email',
        'contact_phone',
        'default_commission_rate',
        'status',
    ];

    protected $casts = [
        'default_commission_rate' => 'float',
    ];

    public function partnerType()
    {
        return $this->belongsTo(PartnerType::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stepPartners()
    {
        return $this->hasMany(ProgrammeStepPartner::class);
    }

    public function reservationShares()
    {
        return $this->hasMany(ProgrammeReservationShare::class);
    }

    public function hasPlatformAccount(): bool
    {
        return $this->user_id !== null;
    }
}
