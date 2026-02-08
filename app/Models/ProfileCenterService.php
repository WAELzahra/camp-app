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
        'name',
        'price',
        'unit',
        'description',
        'is_available',
        'is_standard',
        'min_quantity',
        'max_quantity'
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_standard' => 'boolean'
    ];
    /**
     * Get reservations through service items
     */
    public function reservations()
    {
        return $this->belongsToMany(
            ReservationCentre::class,
            'reservation_service_items',
            'profile_center_service_id',
            'reservation_id'
        )->withPivot([
            'service_name',
            'unit_price',
            'quantity',
            'subtotal',
            'status'
        ])->withTimestamps();
    }
    /**
     * Get reservation service items
     */
    public function reservationServiceItems()
    {
        return $this->hasMany(ReservationServiceItem::class, 'profile_center_service_id');
    }
    /**
     * Get the service category
     */
    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
    
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
       /**
     * Check availability for specific dates and quantity
     */
    public function checkAvailability($startDate, $endDate, $quantity = 1)
    {
        if (!$this->is_available) {
            return [
                'available' => false,
                'message' => 'Service is not available'
            ];
        }
        
        if ($quantity < $this->min_quantity) {
            return [
                'available' => false,
                'message' => "Minimum quantity is {$this->min_quantity}"
            ];
        }
        
        if ($this->max_quantity && $quantity > $this->max_quantity) {
            return [
                'available' => false,
                'message' => "Maximum quantity is {$this->max_quantity}"
            ];
        }
        
        // Check for overlapping reservations
        // You might want to add capacity logic here
        $bookedQuantity = $this->reservationServiceItems()
            ->whereHas('reservation', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'approved')
                      ->where(function($q) use ($startDate, $endDate) {
                          $q->whereBetween('date_debut', [$startDate, $endDate])
                            ->orWhereBetween('date_fin', [$startDate, $endDate])
                            ->orWhere(function($inner) use ($startDate, $endDate) {
                                $inner->where('date_debut', '<=', $startDate)
                                      ->where('date_fin', '>=', $endDate);
                            });
                      });
            })
            ->sum('quantity');
        
        // If you have a capacity field, check against it
        // if (($bookedQuantity + $quantity) > $this->capacity) {
        //     return [
        //         'available' => false,
        //         'message' => 'Not enough capacity'
        //     ];
        // }
        
        return [
            'available' => true,
            'message' => 'Service is available'
        ];
    }
}