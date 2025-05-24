<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materielles_categories extends Model
{
    use HasFactory;
    protected $fillable = [
        "nom",
        "description",
        "creation_date"
    ];
    public function materielle(){
        return $this->hasMany(Materielles::class);
    }
}
