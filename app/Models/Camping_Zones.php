<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Camping_Zones extends Model
{
    use HasFactory;

    protected $table = 'camping_zones';

    protected $fillable = [
        'nom',
        'type_activitee',
        'is_public',
        'description',
        'adresse',
        'danger_level',
        'status',
        'lat',
        'lng',
        'centre_id',
        'region',
        'commune',
        'access_type',
        'max_capacity',
        'opening_season',
        'facilities',
        'activities',
        'is_protected_area',
        'is_closed',
        'closure_reason',
        'closure_start',
        'closure_end',
        'emergency_contacts',
        'map_zoom_level',
        'polygon_coordinates',
        'weather_station_id',
        'last_weather_update',
        'image',
        'source',
        'added_by',
    ];

    protected $casts = [
        'facilities' => 'array',
        'activities' => 'array',
        'emergency_contacts' => 'array',
        'polygon_coordinates' => 'array',
        'is_public' => 'boolean',
        'status' => 'boolean',
        'is_protected_area' => 'boolean',
        'is_closed' => 'boolean',
    ];

    /**
     * Feedbacks liés à la zone
     */
    public function feedbacks()
    {
        return $this->hasMany(Feedbacks::class, 'zone_id')
            ->where('type', 'zone')
            ->where('status', 'approved');
    }

    /**
     * Centre lié
     */
    public function centre()
    {
        return $this->belongsTo(CampingCentre::class, 'centre_id');
    }

    /**
     * Favoris liés (morph)
     */
    public function favoris()
    {
        return $this->morphMany(Favoris::class, 'target');
    }

    /**
     * Champs calculés
     */
    protected $appends = ['centre_nom', 'centre_inscrit'];

    public function getCentreNomAttribute()
    {
        return $this->centre ? $this->centre->nom : null;
    }

    public function getCentreInscritAttribute()
    {
        return $this->centre ? $this->centre->isRegistered() : false;
    }

    
}
