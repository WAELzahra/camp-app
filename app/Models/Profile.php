<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'profiles';

    protected $fillable = [
        'user_id',
        'bio',
        'cover_image',
        'type',
        'activities',
        'cin_path',
        'city',
        'address',
        'is_public',
    ];

    protected $casts = [
        'activities' => 'array',
        'is_public'  => 'boolean',
    ];

    // Relations
    public function profileGuide()
    {
        return $this->hasOne(ProfileGuide::class);
    }

    public function profileCentre()
    {
        return $this->hasOne(ProfileCentre::class);
    }

    public function profileGroupe()
    {
        return $this->hasOne(ProfileGroupe::class);
    }

    public function profileFournisseur()
    {
        return $this->hasOne(ProfileFournisseur::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedbacks::class, 'target_id', 'user_id');
    }

    public function album()
    {
        return $this->hasOne(Album::class, 'profile_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the CIN URL
     */
    public function getCinUrlAttribute(): ?string
    {
        return $this->cin_path ? asset('storage/' . $this->cin_path) : null;
    }

    /**
     * Check if profile has CIN document
     */
    public function hasCin(): bool
    {
        return !is_null($this->cin_path);
    }
}