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
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function payment(){
        return $this->hasOne(Payments::class);
    }
    public function centre(){
        return $this->belongsTo(User::class);
    }
}
