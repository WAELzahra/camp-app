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
}
