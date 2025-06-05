<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservations_materielles extends Model
{
    use HasFactory;
    protected $fillable = [
        "materielle_id",
        "user_id",
        "fournisseur_id",
        "date_debut",
        "date_fin",
        "quantite",
        "montant_payer",
        "status"
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function fournisseur(){
        return $this->belongsTo(User::class);
    }
    public function materielle(){
        return $this->hasOne(Materielles::class);
    }
    public function payment(){
        return $this->hasOne(Payments::class);
    }
    
}
