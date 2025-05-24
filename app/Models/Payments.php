<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    use HasFactory;

    protected $fillable = [
        "montant",
        "description",
        "status"
    ];

    public function reservation_materielle(){
        return $this->hasOne(Reservation_materielle::class);
    }

    public function reservation_event(){
        return $this->hasOne(Reservation_events::class);
    }
    
    public function reservation_centre(){
        return $this->hasOne(Reservation_centre::class);
    }
}
