<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedbacks extends Model
{
    use HasFactory;
    protected $fillable = [
        "user_id",
        "target_id",
        "event_id",
        "zone_id",
        "contenu",
        "response",
        "note",
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function user_target(){
        return $this->belongsTo(User::class);
    }
    public function materielle(){
        return $this->belongsTo(Materielles::class);
    }
    public function event(){
        return $this->belongsTo(Events::class);
    }
    public function reservation(){
        return $this->belongsTo(Reservations_centre::class);
    }
}
