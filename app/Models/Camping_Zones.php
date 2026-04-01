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
        'opening_season',   // ← best_season côté frontend
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
        'facilities'          => 'array',
        'activities'          => 'array',
        'opening_season'      => 'array',
        'emergency_contacts'  => 'array',
        'polygon_coordinates' => 'array',
        'is_public'           => 'boolean',
        'status'              => 'boolean',
        'is_protected_area'   => 'boolean',
        'is_closed'           => 'boolean',
        'lat'                 => 'float',
        'lng'                 => 'float',
        'closure_start'       => 'date',
        'closure_end'         => 'date',
        'last_weather_update' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function centre()
    {
        return $this->belongsTo(CampingCentre::class, 'centre_id');
    }

    public function photos()
    {
        return $this->hasMany(Photo::class, 'camping_zone_id')->orderBy('order');
    }

    public function coverPhoto()
    {
        return $this->hasOne(Photo::class, 'camping_zone_id')->where('is_cover', true);
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedbacks::class, 'zone_id')
            ->where('type', 'zone')
            ->where('status', 'approved');
    }

    public function favoris()
    {
        return $this->morphMany(Favoris::class, 'target');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    // ─── Appends ──────────────────────────────────────────────────────────────

    protected $appends = ['centre_nom', 'centre_inscrit'];

    public function getCentreNomAttribute(): ?string
    {
        return $this->centre ? $this->centre->nom : null;
    }

    public function getCentreInscritAttribute(): bool
    {
        return $this->centre ? $this->centre->isRegistered() : false;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', true)->where('is_closed', false);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByRegion($query, string $region)
    {
        return $query->where('region', $region);
    }
}