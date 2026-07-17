<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeReservation extends Model
{
    protected $fillable = [
        'programme_departure_id',
        'requested_date',
        'user_id',
        'participants_count',
        'total_price',
        'payment_method',
        'payment_option',
        'amount_now',
        'amount_later',
        'payment_reference',
        'balance_due_at',
        'payment_submitted_at',
        'payment_confirmed_at',
        'confirmed_by',
        'status',
        'promo_code_id',
    ];

    protected $casts = [
        'total_price' => 'float',
        'amount_now' => 'float',
        'amount_later' => 'float',
        'requested_date' => 'date',
        'balance_due_at' => 'date',
        'payment_submitted_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
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

    /**
     * The programme_items the camper actually kept at booking time — they
     * can deselect items they don't want (e.g. skip the equipment rental).
     */
    public function selectedItems()
    {
        return $this->belongsToMany(ProgrammeItem::class, 'programme_reservation_items');
    }
}
