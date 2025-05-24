<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation_guide extends Model
{
    use HasFactory;

    protected $fillable = [
        "reserver_id",
        "guide_id",
        "circuit_id",
        "creation_date",
        "type",
        "discription"
    ];
    public function circuit(){
        return $this->hasOne(Circuit::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
}
