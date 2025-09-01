<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZonePolygon extends Model
{
    protected $fillable = ['zone_id', 'coordinates'];

    protected $casts = [
        'coordinates' => 'array', // Laravel convertira automatiquement JSON ↔ array
    ];

    public function zone()
    {
        return $this->belongsTo(Camping_zones::class);
    }
}
