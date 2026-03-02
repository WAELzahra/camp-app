<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Photo; 

class Events extends Model
{
    use HasFactory;

    protected $table = 'events'; 
    
    // Add these to the $fillable array
    protected $fillable = [
        "group_id",
        "title",
        "description",
        "event_type",
        "start_date",
        "end_date",
        "capacity",
        "price",
        "remaining_spots",
        "camping_duration",
        "camping_gear",
        "is_group_travel",
        "departure_city",
        "arrival_city",
        "departure_time",
        "estimated_arrival_time",
        "bus_company",
        "bus_number",
        "city_stops",
        "latitude",
        "longitude",
        "address",
        "difficulty",
        "hiking_duration",
        "elevation_gain",
        "tags",
        "is_active",
        "status",
        "views_count",
    ];
    protected $casts = [
        'city_stops' => 'array',
        'tags' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'departure_time' => 'datetime',
        'estimated_arrival_time' => 'datetime',
        'is_active' => 'boolean',
        'is_group_travel' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'price' => 'decimal:2',
        'hiking_duration' => 'decimal:2',
        'elevation_gain' => 'integer',
    ];
    /**
     * Get the group (user) that owns this event
     */
    public function group()
    {
        return $this->belongsTo(User::class, 'group_id');
    }

    /**
     * Get all feedbacks for this event
     */
    public function feedbacks()
    {
        return $this->hasMany(Feedbacks::class, 'event_id');
    }

    /**
     * Get all reservations for this event
     */
    public function reservation()
    {
        return $this->hasMany(Reservations_events::class, 'event_id');
    }

    /**
     * Get all photos for this event - FIXED: use Photo model (singular)
     */
    public function photos()
    {
        return $this->hasMany(Photo::class, 'event_id'); 
    }

    /**
     * Get users interested in this event
     */
    public function interestedUsers()
    {
        return $this->belongsToMany(User::class, 'interested_events', 'event_id', 'user_id');
    }

    /**
     * Check if event is a camping type
     */
    public function isCamping(): bool
    {
        return $this->event_type === 'camping';
    }

    /**
     * Check if event is a hiking type
     */
    public function isHiking(): bool
    {
        return $this->event_type === 'hiking';
    }

    /**
     * Check if event is a voyage type
     */
    public function isVoyage(): bool
    {
        return $this->event_type === 'voyage';
    }

    /**
     * Check if event has group travel (for camping events)
     */
    public function hasGroupTravel(): bool
    {
        return $this->is_group_travel;
    }

    /**
     * Get available spots
     */
    public function getAvailableSpotsAttribute(): int
    {
        return $this->remaining_spots;
    }

    /**
     * Check if event is fully booked
     */
    public function isFullyBooked(): bool
    {
        return $this->remaining_spots <= 0;
    }

    /**
     * Check if event has started
     */
    public function hasStarted(): bool
    {
        return now()->greaterThanOrEqualTo($this->start_date);
    }

    /**
     * Check if event has ended
     */
    public function hasEnded(): bool
    {
        return now()->greaterThan($this->end_date);
    }

    /**
     * Get event duration in days
     */
    public function getDurationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Scope for active events
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for events by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope for upcoming events
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    /**
     * Scope for ongoing events
     */
    public function scopeOngoing($query)
    {
        return $query->where('start_date', '<=', now())
                     ->where('end_date', '>=', now());
    }

    /**
     * Scope for past events
     */
    public function scopePast($query)
    {
        return $query->where('end_date', '<', now());
    }

    /**
     * Scope for events with available spots
     */
    public function scopeWithAvailableSpots($query)
    {
        return $query->where('remaining_spots', '>', 0);
    }

    /**
     * Scope for events in a specific city
     */
    public function scopeInCity($query, $city)
    {
        return $query->where('address', 'like', "%{$city}%")
                     ->orWhere('departure_city', $city)
                     ->orWhere('arrival_city', 'like', "%{$city}%");
    }
    /**
     * Get difficulty level as readable string
     */
    public function getDifficultyLabelAttribute(): string
    {
        $difficulties = [
            'easy' => 'Easy',
            'moderate' => 'Moderate',
            'difficult' => 'Difficult',
            'expert' => 'Expert',
        ];
        
        return $difficulties[$this->difficulty] ?? ucfirst($this->difficulty);
    }

    /**
     * Get hiking duration formatted
     */
    public function getFormattedHikingDurationAttribute(): string
    {
        if (!$this->hiking_duration) return 'N/A';
        
        $hours = floor($this->hiking_duration);
        $minutes = round(($this->hiking_duration - $hours) * 60);
        
        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}min";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}min";
        }
    }

    /**
     * Get elevation gain formatted
     */
    public function getFormattedElevationAttribute(): string
    {
        if (!$this->elevation_gain) return 'N/A';
        return number_format($this->elevation_gain) . ' m';
    }

    /**
     * Get all hiking-specific details as an array
     */
    public function getHikingDetailsAttribute(): array
    {
        return [
            'difficulty' => $this->difficulty,
            'difficulty_label' => $this->difficulty_label,
            'duration' => $this->hiking_duration,
            'duration_formatted' => $this->formatted_hiking_duration,
            'elevation' => $this->elevation_gain,
            'elevation_formatted' => $this->formatted_elevation,
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'address' => $this->address,
            ],
        ];
    }
}