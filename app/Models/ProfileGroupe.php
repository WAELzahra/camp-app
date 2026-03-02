<?php

namespace App\Models;

use App\Models\Profile;
use App\Models\Album;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileGroupe extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'nom_groupe',
        'id_album_photo',
        'id_annonce',
        'cin_responsable',
        'adresse',
        // Nouveaux champs documents
        'patente_path',
        'patente_filename',
        'cin_responsable_path',
        'cin_responsable_filename',
    ];

    // Relations existantes
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows')->withTimestamps();
    }

    public function album()
    {
        return $this->hasOne(Album::class, 'id_album_photo', 'id');
    }

    /**
     * Get the patente URL
     */
    public function getPatenteUrlAttribute(): ?string
    {
        return $this->patente_path ? asset('storage/' . $this->patente_path) : null;
    }

    /**
     * Get the CIN responsable URL
     */
    public function getCinResponsableUrlAttribute(): ?string
    {
        return $this->cin_responsable_path ? asset('storage/' . $this->cin_responsable_path) : null;
    }

    /**
     * Check if profile has patente
     */
    public function hasPatente(): bool
    {
        return !is_null($this->patente_path);
    }

    /**
     * Check if profile has CIN responsable
     */
    public function hasCinResponsable(): bool
    {
        return !is_null($this->cin_responsable_path);
    }
}