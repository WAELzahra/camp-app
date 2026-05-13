<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materielles_categories extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'trip_contexts',
        'icon',
        'is_safety_critical',
    ];

    protected $casts = [
        'trip_contexts'     => 'array',
        'is_safety_critical'=> 'boolean',
    ];

    /**
     * All materiels belonging to this category.
     */
    public function materielles()
    {
        // Fixed: was hasMany(Materielles::class) — correct, but method was named materielle (singular)
        return $this->hasMany(Materielles::class, 'category_id');
    }
}