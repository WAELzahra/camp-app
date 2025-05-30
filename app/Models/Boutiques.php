<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boutiques extends Model
{
    use HasFactory;
    protected $fillable = [
        "users_id",
        "nom_boutique",
        "description",
        "status"
    ];

    public function fournisseur(){
        return $this->belongsTo(User::class);
    }
    
}
