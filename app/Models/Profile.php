<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bio',
        'cover_image',
        'immatricule',
        'type',
        'activities',
        // Nouveaux champs documents
        'cin_path',
        'cin_filename',
        'adresse', // Ajout de l'adresse si manquant
    ];

    protected $casts = [
        'activities' => 'array',
    ];

    // Relations existantes
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

    // Relation feedbacks reçus par ce profil (via user_id)
    public function feedbacks()
    {
        return $this->hasMany(\App\Models\Feedbacks::class, 'target_id', 'user_id');
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