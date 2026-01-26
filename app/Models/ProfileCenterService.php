<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProfileCenterService extends Pivot
{
    protected $table = 'profile_center_services';
    
    public $incrementing = true;
    
    protected $fillable = [
        'profile_center_id',
        'service_category_id',
        'price',
        'unit',
        'description',
        'is_available',
        'min_quantity',
        'max_quantity',
        'is_standard'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_standard' => 'boolean',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
    ];

    /**
     * Get the profile center
     */
    public function profileCenter()
    {
        return $this->belongsTo(ProfileCentre::class, 'profile_center_id');
    }

    /**
     * Get the service category
     */
    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2) . ' TND';
    }

    /**
     * Get price per unit
     */
    public function getPricePerUnitAttribute(): string
    {
        return $this->formatted_price . '/' . $this->unit;
    }

    /**
     * Check if service is available
     */
    public function isAvailable(): bool
    {
        return $this->is_available;
    }

    /**
     * Check if this is the standard service
     */
    public function isStandard(): bool
    {
        return $this->is_standard;
    }
}