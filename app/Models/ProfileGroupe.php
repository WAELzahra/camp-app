<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileGroupe extends Model
{
    use HasFactory;

    protected $table = 'profile_groupes';

    protected $fillable = [
        'profile_id',
        'nom_groupe',
        'id_album_photo',
        'id_annonce',
        'patente_path',
    ];

    // Relations
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
        return $this->hasOne(Album::class, 'id', 'id_album_photo');
    }

    /**
     * Get the patente URL
     */
    public function getPatenteUrlAttribute(): ?string
    {
        return $this->patente_path ? asset('storage/' . $this->patente_path) : null;
    }

    /**
     * Check if profile has patente
     */
    public function hasPatente(): bool
    {
        return !is_null($this->patente_path);
    }
}