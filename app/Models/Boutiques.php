<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boutiques extends Model
{
    use HasFactory;
    protected $primaryKey = 'fournisseur_id';  // <-- important
    public $incrementing = false;  
    protected $fillable = [
        "fournisseur_id",
        "nom_boutique",
        "description",
        "status"
    ];

    public function fournisseur(){
        return $this->belongsTo(User::class);
    }
    
}
