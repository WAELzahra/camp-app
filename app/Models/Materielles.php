<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materielles extends Model
{
    use HasFactory;
    protected $fillable = [
        "fournisseur_id",
        "category_id",
        "nom",
        "description",
        "tarif_nuit",
        "quantite_dispo",
        "quantite_total",
        "type"
    ];

    public function category(){
        return $this->hasOne(Materielles_categories::class);
    }

    public function photos(){
        return $this->hasMany(Photos::class);
    }
    public function feedbacks(){
        return $this->hasMany(Feedbacks::class);
    }
    public function fournisseur(){
        return $this->belongsTo(Feedbacks::class);

    }
    
}
