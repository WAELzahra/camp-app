<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservations_envets extends Model
{
    use HasFactory;
    protected $fillable = [
        "group_id",
        "user_id",
        "event_id",
        "nbr_place",
        "payment_id"
    ];

    public function group(){
        return $this->belongsTo(User::class);
    }
    
    public function event(){
        return $this->belongsTo(Events::class);
    }
    
    public function payment(){
        return $this->hasOne(Payments::class);
    }
}
