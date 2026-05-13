<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation_guide extends Model
{
    use HasFactory;

    protected $table = 'reservation_guides';
    
    protected $fillable = [
        "reserver_id",
        "guide_id",
        "circuit_id",
        "creation_date",
        "type",
        "discription"
    ];

    /**
     * Relation avec le circuit
     * Une réservation appartient à un circuit
     */
    public function circuit()
    {
        return $this->belongsTo(Circuit::class, 'circuit_id');
    }

    /**
     * Relation avec l'utilisateur qui a fait la réservation
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'reserver_id');
    }

    /**
     * Relation avec le guide (utilisateur)
     */
    public function guide()
    {
        return $this->belongsTo(User::class, 'guide_id');
    }
}