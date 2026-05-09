<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileCampeur extends Model
{
    use HasFactory;

    protected $table = 'profile_campeurs';

    protected $fillable = [
        'profile_id',
        'skill_level',
        'comfort_level',
        'budget_range',
        'preferred_trip_styles',
        'preferred_activities',
        'gear_preferences',
        'total_trips',
    ];

    protected $casts = [
        'preferred_trip_styles' => 'array',
        'preferred_activities'  => 'array',
        'gear_preferences'      => 'array',
        'total_trips'           => 'integer',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
