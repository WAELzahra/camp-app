<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeReservation extends Model
{
    protected $fillable = [
        'programme_departure_id',
        'user_id',
        'participants_count',
        'total_price',
        'payment_method',
        'payment_option',
        'amount_now',
        'amount_later',
        'status',
        'promo_code_id',
    ];

    protected $casts = [
        'total_price' => 'float',
        'amount_now' => 'float',
        'amount_later' => 'float',
    ];

    public function departure()
    {
        return $this->belongsTo(ProgrammeDeparture::class, 'programme_departure_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function shares()
    {
        return $this->hasMany(ProgrammeReservationShare::class);
    }
}
