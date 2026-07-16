<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Programme extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'cover_image',
        'status',
        'publish_at',
        'min_participants',
        'max_participants',
        'cancellation_policy_id',
        'created_by',
    ];

    protected $casts = [
        'publish_at' => 'datetime',
    ];

    public function rules()
    {
        return $this->hasMany(ProgrammeRule::class)->orderBy('sort_order');
    }

    public function items()
    {
        return $this->hasMany(ProgrammeItem::class)->orderBy('day_offset')->orderBy('sort_order');
    }

    public function departures()
    {
        return $this->hasMany(ProgrammeDeparture::class);
    }

    public function cancellationPolicy()
    {
        return $this->belongsTo(CancellationPolicy::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Sum of all item prices — the default per-participant price when a
     * departure has no price_override.
     */
    public function basePrice(): float
    {
        return (float) $this->items->sum('price');
    }
}
