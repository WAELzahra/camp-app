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
        "danger_level" 
    ];

    public function event()
    {
        return $this->belongsToMany(Events::class);
    }

    /**
     * Un circuit peut avoir plusieurs réservations de guides
     */
    public function reservation_guide()
    {
        return $this->hasMany(Reservation_guide::class, 'circuit_id');
    }
    
    public function guideReservations()
    {
        return $this->hasMany(Reservation_guide::class, 'circuit_id');
    }
}