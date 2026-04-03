<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'applicable_to',
        'min_price',
        'max_uses',
        'used_count',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_price'      => 'decimal:2',
        'max_uses'       => 'integer',
        'used_count'     => 'integer',
        'is_active'      => 'boolean',
        'expires_at'     => 'datetime',
    ];

    /**
     * Check if the promo code is currently valid.
     *
     * @param  string  $reservationType  'centre'|'materiel'|'event'
     * @param  float   $price            Base price before discount
     * @return array   ['valid' => bool, 'reason' => string|null]
     */
    public function isValid(string $reservationType = 'all', float $price = 0): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'reason' => 'This promo code is disabled.'];
        }

        if ($this->expires_at && Carbon::now()->isAfter($this->expires_at)) {
            return ['valid' => false, 'reason' => 'This promo code has expired.'];
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return ['valid' => false, 'reason' => 'This promo code has reached its usage limit.'];
        }

        if ($this->applicable_to !== 'all' && $this->applicable_to !== $reservationType) {
            return ['valid' => false, 'reason' => "This promo code is not applicable to {$reservationType} reservations."];
        }

        if ($this->min_price !== null && $price < $this->min_price) {
            return ['valid' => false, 'reason' => "A minimum order of {$this->min_price} TND is required for this promo code."];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Calculate the discount amount for a given price.
     * The discount cannot exceed the total price.
     */
    public function calculateDiscount(float $price): float
    {
        if ($this->discount_type === 'percentage') {
            $discount = $price * ($this->discount_value / 100);
        } else {
            $discount = (float) $this->discount_value;
        }

        return round(min($discount, $price), 2);
    }

    /**
     * Increment the used_count atomically.
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
