<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservations_events extends Model
{
    use HasFactory;

protected $table = 'reservations_events'; 

    protected $fillable = [
        'group_id',
        'user_id',
        'event_id',
        'nbr_place',
        'payment_id',
        'status',
        'name',
        'email',
        'phone',
        'created_by',
        'promo_code_id',
        'discount_amount',
        'payment_method',
        'platform_fee_amount',
        'platform_fee_rate',
        'group_skill_level',
        'trip_purpose',
        'payment_reference',
        'payment_option',
        'amount_now',
        'amount_later',
        'balance_due_at',
        'payment_submitted_at',
        'payment_confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'platform_fee_amount' => 'float',
        'platform_fee_rate'   => 'float',
    ];

    // 🔁 Relation : le groupe organisateur
    public function group()
    {
        return $this->belongsTo(User::class, 'group_id');
    }

    // 🔁 Relation : l’utilisateur ayant réservé
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 🔁 Relation : l’événement lié
    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    // 🔁 Relation : paiement associé
    public function payment()
    {
        return $this->belongsTo(Payments::class, 'payment_id');
    }

    // 🔁 Alias: organizer (same as group)
    public function organizer()
    {
        return $this->belongsTo(User::class, 'group_id');
    }

    // 🔁 Optional equipment booked alongside this event reservation
    public function materials()
    {
        return $this->hasMany(EventReservationMaterial::class, 'event_reservation_id');
    }

    public function services()
    {
        return $this->hasMany(EventReservationService::class, 'event_reservation_id');
    }
}
