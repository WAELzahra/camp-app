<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Camping_Zones;

class Favoris extends Model
{
    use HasFactory;

    // Seuls ces champs peuvent être remplis via create() ou update()
    protected $fillable = [
        "user_id",
        "target_id",
        "type"
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation morph pour la cible (zone, centre, event)
  // App\Models\Favoris.php
        public function getTargetModel()
        {
            switch ($this->type) {
                case 'zone':
                    return Camping_Zones::find($this->target_id);
                case 'centre':
                    return CampingCentre::find($this->target_id);
                case 'event':
                    return Events::find($this->target_id);
                default:
                    return null;
            }
        }

         public function target()
    {
        switch ($this->type) {
            case 'centre':
                return $this->belongsTo(\App\Models\CampingCentre::class, 'target_id');
            case 'zone':
                return $this->belongsTo(\App\Models\Camping_Zones::class, 'target_id');
            default:
                return null;
        }
    }

        



}
