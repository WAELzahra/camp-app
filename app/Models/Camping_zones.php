<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Camping_zones extends Model
{
    use HasFactory;

    protected $fillable = [
        "nom",
        "type_activitee",
        "is_public",
        "description",
        "adresse",
        "danger_level",
        "status",

    ];
    public function feedbacks(){
        return $this->hasMany(Feedbacks::class);
    }
}
