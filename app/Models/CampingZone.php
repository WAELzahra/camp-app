<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampingZone extends Model
{
    use HasFactory;

    protected $table = 'camping_zones';

    protected $fillable = [
        'nom',
        'city',
        'region',
        'commune',
        'description',
        'full_description',
        'terrain',
        'difficulty',
        'lat',
        'lng',
        'adresse',
        'distance',
        'altitude',
        'access_type',
        'accessibility',
        'rating',
        'reviews_count',
        'best_season',
        'activities',
        'facilities',
        'rules',
        'contact_phone',
        'contact_email',
        'contact_website',
        'is_public',
        'status',
        'is_protected_area',
        'is_closed',
        'closure_reason',
        'closure_start',
        'closure_end',
        'danger_level',
        'max_capacity',
        'map_zoom_level',
        'polygon_coordinates',
        'emergency_contacts',
        'weather_station_id',
        'last_weather_update',
        'source',
        'centre_id',
        'added_by',
    ];

    protected $casts = [
        'best_season'          => 'array',
        'activities'           => 'array',
        'facilities'           => 'array',
        'rules'                => 'array',
        'emergency_contacts'   => 'array',
        'polygon_coordinates'  => 'array',
        'is_public'            => 'boolean',
        'status'               => 'boolean',
        'is_protected_area'    => 'boolean',
        'is_closed'            => 'boolean',
        'closure_start'        => 'date',
        'closure_end'          => 'date',
        'last_weather_update'  => 'datetime',
        'rating'               => 'float',
        'lat'                  => 'float',
        'lng'                  => 'float',
    ];


    /**
     * Photos from the shared photos table (gallery).
     * Cover photo is identified by is_cover = true.
     */
    public function photos()
    {
        return $this->hasMany(Photo::class, 'camping_zone_id')->orderBy('order');
    }

    /**
     * Cover photo only.
     */
    public function coverPhoto()
    {
        return $this->hasOne(Photo::class, 'camping_zone_id')->where('is_cover', true);
    }

    /**
     * The camping centre this zone belongs to (optional).
     */
    public function centre()
    {
        return $this->belongsTo(CampingCentre::class, 'centre_id');
    }

    /**
     * The user who added this zone.
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Feedbacks / reviews for this zone.
     */
    public function feedbacks()
    {
        return $this->hasMany(Feedbacks::class, 'zone_id');
    }


    public function scopeOpen($query)
    {
        return $query->where('status', true)->where('is_closed', false);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public function scopeByRegion($query, string $region)
    {
        return $query->where('region', $region);
    }


    /**
     * Returns the contact array in the shape ZoneDetail expects:
     * { phone, email, website }
     */
    public function getContactAttribute(): array
    {
        return array_filter([
            'phone'   => $this->contact_phone,
            'email'   => $this->contact_email,
            'website' => $this->contact_website,
        ]);
    }

    /**
     * Returns coordinates in the shape ZoneDetail expects:
     * { lat, lng }
     */
    public function getCoordinatesAttribute(): array
    {
        return [
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
        ];
    }

    /**
     * All gallery image URLs from the photos table.
     */
    public function getGalleryImagesAttribute(): array
    {
        return $this->photos->pluck('path_to_img')->toArray();
    }
}