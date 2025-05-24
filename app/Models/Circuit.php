<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Circuit extends Model
{
    use HasFactory;
    protected $fillable = [
        "adresse_debut_circuit",
        "adresse_fin_circuit",
        "description",
        "difficulty",
        "distance_km",
        "estimation_temps",
        "difficulte",
        "danger_level"

    ];

    public function event(){
        return $this->belongsToMany(Events::class);
    }

    public function reservation_guide(){
        return $this->belongsToMany(Reservation_guide::class);
    }
}
