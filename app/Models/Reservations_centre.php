<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservations_centre extends Model
{
    use HasFactory;
    protected $fillable = [
        "user_id",
        "centre_id",
        "date_debut",
        "date_fin",
        "nbr_place",
        "note",
        "type",
        "status",
        "payments_id"
    ];

    public function payment(){
        return $this->hasOne(Payments::class);
    }

    // The user who made the reservation
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    // The user who owns the centre
    public function centre() {
        return $this->belongsTo(User::class, 'centre_id');
    }
}
