<?php

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
        'adresse',
        // Nouveaux champs documents
        'certificat_path',
        'certificat_filename',
        'certificat_type',
        'certificat_expiration',
    ];

    protected $casts = [
        'certificat_expiration' => 'date',
        'tarif' => 'decimal:2',
        'experience' => 'integer',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the certificat URL
     */
    public function getCertificatUrlAttribute(): ?string
    {
        return $this->certificat_path ? asset('storage/' . $this->certificat_path) : null;
    }

    /**
     * Check if certificat is valid (not expired)
     */
    public function isCertificatValid(): bool
    {
        if (!$this->certificat_expiration) {
            return true; // Pas de date d'expiration = toujours valide
        }
        return $this->certificat_expiration->isFuture();
    }

    /**
     * Check if certificat is expiring soon (within 30 days)
     */
    public function isCertificatExpiringSoon(): bool
    {
        if (!$this->certificat_expiration) {
            return false;
        }
        return $this->certificat_expiration->isFuture() && 
               $this->certificat_expiration->diffInDays(now()) <= 30;
    }

    /**
     * Check if profile has certificat
     */
    public function hasCertificat(): bool
    {
        return !is_null($this->certificat_path);
    }
}