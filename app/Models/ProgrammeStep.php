<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeStep extends Model
{
    protected $fillable = [
        'programme_id',
        'sort_order',
        'title',
        'description',
        'day_offset',
        'start_time',
        'end_time',
        'location_label',
        'location_lat',
        'location_lng',
    ];

    protected $casts = [
        'location_lat' => 'float',
        'location_lng' => 'float',
    ];

    public function programme()
    {
        return $this->belongsTo(Programme::class);
    }

    public function stepPartners()
    {
        return $this->hasMany(ProgrammeStepPartner::class);
    }
}
