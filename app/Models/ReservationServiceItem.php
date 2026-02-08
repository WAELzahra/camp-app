<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationServiceItem extends Model
{
    use HasFactory;

    protected $table = 'reservation_service_items';
    
    protected $fillable = [
        'reservation_id',
        'profile_center_service_id',
        'service_name',
        'service_description',
        'unit_price',
        'unit',
        'quantity',
        'subtotal',
        'service_date_debut',
        'service_date_fin',
        'notes',
        'status',
        'rejected_by',
        'rejection_reason',
        'rejected_at'
    ];
    
    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'service_date_debut' => 'date',
        'service_date_fin' => 'date',
        'rejected_at' => 'datetime'
    ];
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    
    // Rejected by constants
    const REJECTED_BY_CENTER = 'center';
    const REJECTED_BY_USER = 'user';
    
    /**
     * Get the reservation
     */
    public function reservation()
    {
        return $this->belongsTo(Reservations_centre::class, 'reservation_id');
    }
    
    /**
     * Get the service
     */
    public function service()
    {
        return $this->belongsTo(ProfileCenterService::class, 'profile_center_service_id');
    }
    
    /**
     * Mark service as rejected by center
     */
    public function markAsRejectedByCenter($reason)
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejected_by = self::REJECTED_BY_CENTER;
        $this->rejection_reason = $reason;
        $this->rejected_at = now();
        return $this->save();
    }
    
    /**
     * Check if rejected by center
     */
    public function isRejectedByCenter()
    {
        return $this->status === self::STATUS_REJECTED && 
               $this->rejected_by === self::REJECTED_BY_CENTER;
    }
    
    /**
     * Get rejection information
     */
    public function getRejectionInfo()
    {
        if ($this->status !== self::STATUS_REJECTED || !$this->rejected_by) {
            return null;
        }

        return [
            'by' => $this->rejected_by,
            'reason' => $this->rejection_reason,
            'at' => $this->rejected_at,
            'formatted' => "Rejected by " . ucfirst($this->rejected_by) . 
                          ": " . ($this->rejection_reason ?: 'No reason provided')
        ];
    }
    
    /**
     * Calculate subtotal automatically
     */
    public function calculateSubtotal()
    {
        $this->subtotal = $this->unit_price * $this->quantity;
        return $this->subtotal;
    }
    
    /**
     * Get the service category through the service
     */
    public function getCategoryAttribute()
    {
        return $this->service->category ?? null;
    }
}