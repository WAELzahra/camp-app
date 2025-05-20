<?php

// app/Models/ProfileGuide.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileGuide extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'experience',
        'tarif',
        'zone_travail',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
