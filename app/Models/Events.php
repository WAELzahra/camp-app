<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Events extends Model
{
    use HasFactory;

    protected $fillable = [
        "group_id",
        "description",
        "category",
        "date_sortie",
        "date_retoure",
        "ville_passente",
        "nbr_place_total",
        "nbr_place_restante",
        "prix_place",
        "circuit_id",
    ];

    public function feedbacks(){
        return $this->hasMany(Feedbacks::class);
    }

    public function group(){
        return $this->belongsTo(User::class);
    }
    public function circuit(){
        return $this->hasMany(Circuit::class);
    }
    public function reservation(){
        return $this->hasMany(Reservations_envets::class);
    }
    public function photos(){
        return $this->hasMany(Photos::class);
    }
}
