<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileCenterEquipment extends Model
{
    protected $table = 'profile_center_equipment';
    
    protected $fillable = [
        'profile_center_id',
        'type',
        'is_available',
        'notes'
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    /**
     * Equipment type translations
     */
    const TYPE_TRANSLATIONS = [
        'toilets' => 'Toilettes',
        'drinking_water' => 'Eau potable',
        'electricity' => 'Électricité',
        'parking' => 'Parking',
        'wifi' => 'WiFi',
        'showers' => 'Douches',
        'security' => 'Sécurité',
        'kitchen' => 'Cuisine',
        'bbq_area' => 'Zone BBQ',
        'swimming_pool' => 'Piscine',
    ];

    /**
     * Equipment type icons
     */
    const TYPE_ICONS = [
        'toilets' => 'fas fa-restroom',
        'drinking_water' => 'fas fa-tint',
        'electricity' => 'fas fa-bolt',
        'parking' => 'fas fa-parking',
        'wifi' => 'fas fa-wifi',
        'showers' => 'fas fa-shower',
        'security' => 'fas fa-shield-alt',
        'kitchen' => 'fas fa-utensils',
        'bbq_area' => 'fas fa-fire',
        'swimming_pool' => 'fas fa-swimming-pool',
    ];

    /**
     * Get the profile center
     */
    public function profileCenter(): BelongsTo
    {
        return $this->belongsTo(ProfileCentre::class, 'profile_center_id');
    }

    /**
     * Get translated type name
     */
    public function getTranslatedTypeAttribute(): string
    {
        return self::TYPE_TRANSLATIONS[$this->type] ?? $this->type;
    }

    /**
     * Get icon for equipment type
     */
    public function getIconAttribute(): string
    {
        return self::TYPE_ICONS[$this->type] ?? 'fas fa-check-circle';
    }

    /**
     * Check if equipment is available
     */
    public function isAvailable(): bool
    {
        return $this->is_available;
    }

    /**
     * Scope available equipment
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope by type
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }
}