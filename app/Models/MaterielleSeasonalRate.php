<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterielleSeasonalRate extends Model
{
    protected $table = 'materielle_seasonal_rates';

    protected $fillable = [
        'materielle_id',
        'name',
        'start_month',
        'start_day',
        'end_month',
        'end_day',
        'price_weekday',
        'price_weekend',
    ];

    protected $casts = [
        'start_month'   => 'integer',
        'start_day'     => 'integer',
        'end_month'     => 'integer',
        'end_day'       => 'integer',
        'price_weekday' => 'float',
        'price_weekend' => 'float',
    ];

    public function materielle()
    {
        return $this->belongsTo(Materielles::class, 'materielle_id');
    }

    /**
     * Whether a calendar date falls inside this recurring yearly range.
     * Handles ranges that wrap across new year (e.g. 15 Dec → 10 Jan).
     */
    public function coversDate(\DateTimeInterface $date): bool
    {
        $md    = (int) $date->format('n') * 100 + (int) $date->format('j');
        $start = $this->start_month * 100 + $this->start_day;
        $end   = $this->end_month   * 100 + $this->end_day;

        return $start <= $end
            ? ($md >= $start && $md <= $end)
            : ($md >= $start || $md <= $end); // wraps over new year
    }
}
