<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Photos;

class Events extends Model
{
    use HasFactory;

    protected $fillable = [
        "title",
        "group_id",
        "description",
        "category",
        "date_sortie",
        "date_retoure",
        "ville_passente",
          'tags', 
        "nbr_place_total",
        "nbr_place_restante",
        "prix_place",
        "circuit_id",
        'is_active',
        'status'
    ];
    protected $casts = [
    'ville_passente' => 'array',
];

    


    public function feedbacks(){
        return $this->hasMany(Feedbacks::class);
    }

    public function group(){
        return $this->belongsTo(User::class);
    }
    public function circuit()
    {
        return $this->belongsTo(Circuit::class, 'circuit_id');
    }
    // public function reservation(){
    //     return $this->hasMany(Reservations_events::class);
    // }

    public function reservation()
{
    return $this->hasMany(Reservations_events::class, 'event_id');
}
   public function photos()
{
    return $this->hasMany(Photos::class, 'event_id');
}

public function interestedUsers()
{
    return $this->belongsToMany(User::class, 'interested_events', 'event_id', 'user_id');
}



}
