<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservations_centre extends Model
{
    use HasFactory;

    protected $table = 'reservations_centres';
    
    protected $fillable = [
        'user_id',
        'centre_id',
        'date_debut',
        'date_fin',
        'nbr_place',
        'note',
        'type',
        'status',
        'payments_id',
        'total_price',
        'service_count',
        'last_modified_by',
        'last_modified_at'
    ];
    
    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'total_price' => 'decimal:2',
        'last_modified_at' => 'datetime'
    ];
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_MODIFIED = 'modified';
    const STATUS_CANCELED = 'canceled';
    
    // Modified by constants
    const MODIFIED_BY_CENTER = 'center';
    const MODIFIED_BY_USER = 'user';
    
    /**
     * Get all service items for this reservation
     */
    public function serviceItems()
    {
        return $this->hasMany(ReservationServiceItem::class, 'reservation_id');
    }
    
    /**
     * Get services through service items
     */
    public function services()
    {
        return $this->belongsToMany(
            ProfileCenterService::class,
            'reservation_service_items',
            'reservation_id',
            'profile_center_service_id'
        )->withPivot([
            'service_name',
            'service_description',
            'unit_price',
            'unit',
            'quantity',
            'subtotal',
            'service_date_debut',
            'service_date_fin',
            'notes',
            'status'
        ])->withTimestamps();
    }
    
    /**
     * Get the user who made the reservation
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Get the center for this reservation
     */
    public function centre()
    {
        return $this->belongsTo(User::class, 'centre_id');
    }
    
    /**
     * Get the payment for this reservation
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payments_id');
    }
    
    /**
     * Mark reservation as modified by center
     */
    public function markAsModifiedByCenter()
    {
        $this->status = self::STATUS_MODIFIED;
        $this->last_modified_by = self::MODIFIED_BY_CENTER;
        $this->last_modified_at = now();
        return $this->save();
    }
    
    /**
     * Check if modified by center
     */
    public function isModifiedByCenter()
    {
        return $this->status === self::STATUS_MODIFIED && 
               $this->last_modified_by === self::MODIFIED_BY_CENTER;
    }
    
    /**
     * Get modification information
     */
    public function getModificationInfo()
    {
        if (!$this->last_modified_by) {
            return null;
        }

        return [
            'by' => $this->last_modified_by,
            'at' => $this->last_modified_at,
            'formatted' => "Modified by " . ucfirst($this->last_modified_by) . 
                          " on " . ($this->last_modified_at ? $this->last_modified_at->format('M j, Y \a\t g:i A') : '')
        ];
    }
    
    /**
     * Calculate and update total price
     */
    public function calculateTotal()
    {
        $total = $this->serviceItems()->sum('subtotal');
        $this->total_price = $total;
        $this->service_count = $this->serviceItems()->count();
        $this->save();
        
        return $total;
    }
    
    /**
     * Get primary service type (e.g., for display purposes)
     */
    public function getPrimaryServiceAttribute()
    {
        // Find the most expensive or first service as primary
        return $this->serviceItems()
            ->orderBy('subtotal', 'desc')
            ->first();
    }
    
    /**
     * Get service types as comma-separated list
     */
    public function getServiceTypesAttribute()
    {
        return $this->serviceItems()
            ->with('service.category')
            ->get()
            ->map(function ($item) {
                return $item->service->category->name ?? $item->service_name;
            })
            ->unique()
            ->implode(', ');
    }
    
    /**
     * Get all services grouped by category
     */
    public function getServicesByCategoryAttribute()
    {
        return $this->serviceItems()
            ->with('service.category')
            ->get()
            ->groupBy(function ($item) {
                return $item->service->category->name ?? 'Other';
            });
    }
    
    /**
     * Get accepted services
     */
    public function acceptedServices()
    {
        return $this->serviceItems()->where('status', 'approved');
    }
    
    /**
     * Get rejected services
     */
    public function rejectedServices()
    {
        return $this->serviceItems()->where('status', 'rejected');
    }
}