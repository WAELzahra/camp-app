<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_standard',
        'suggested_price',
        'min_price',
        'unit',
        'icon',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_standard' => 'boolean',
        'is_active' => 'boolean',
        'suggested_price' => 'decimal:2',
        'min_price' => 'decimal:2',
    ];

    /**
     * Get the profile centers that offer this service
     */
    public function profileCenters(): BelongsToMany
    {
        return $this->belongsToMany(ProfileCentre::class, 'profile_center_services')
                    ->using(ProfileCenterService::class)
                    ->withPivot('price', 'unit', 'description', 'is_available', 'min_quantity', 'max_quantity', 'is_standard')
                    ->withTimestamps();
    }

    /**
     * Get the center services pivot records
     */
    public function centerServices(): HasMany
    {
        return $this->hasMany(ProfileCenterService::class, 'service_category_id');
    }

    /**
     * Scope active services
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope standard services (basic camping)
     */
    public function scopeStandard($query)
    {
        return $query->where('is_standard', true);
    }

    /**
     * Scope additional (non-standard) services
     */
    public function scopeAdditional($query)
    {
        return $query->where('is_standard', false);
    }

    /**
     * Scope ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get formatted price
     */
    public function getFormattedSuggestedPriceAttribute(): string
    {
        return number_format($this->suggested_price, 2) . ' TND';
    }

    /**
     * Get icon class for display
     */
    public function getIconClassAttribute(): string
    {
        $iconMap = [
            'tent' => 'fas fa-campground',
            'coffee' => 'fas fa-coffee',
            'utensils' => 'fas fa-utensils',
            'moon' => 'fas fa-moon',
            'bed' => 'fas fa-bed',
            'map' => 'fas fa-map-marked-alt',
            'flame' => 'fas fa-fire',
            'car' => 'fas fa-car',
            'chair' => 'fas fa-chair',
        ];

        return $iconMap[$this->icon] ?? 'fas fa-star';
    }
}