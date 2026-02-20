<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileFournisseur extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'intervale_prix',
        'product_category',
        // Nouveaux champs documents
        'cin_commercant_path',
        'cin_commercant_filename',
        'registre_commerce_path',
        'registre_commerce_filename',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the CIN commerçant URL
     */
    public function getCinCommercantUrlAttribute(): ?string
    {
        return $this->cin_commercant_path ? asset('storage/' . $this->cin_commercant_path) : null;
    }

    /**
     * Get the registre commerce URL
     */
    public function getRegistreCommerceUrlAttribute(): ?string
    {
        return $this->registre_commerce_path ? asset('storage/' . $this->registre_commerce_path) : null;
    }

    /**
     * Check if profile has CIN commerçant
     */
    public function hasCinCommercant(): bool
    {
        return !is_null($this->cin_commercant_path);
    }

    /**
     * Check if profile has registre commerce
     */
    public function hasRegistreCommerce(): bool
    {
        return !is_null($this->registre_commerce_path);
    }

    /**
     * Check if profile has all required documents
     */
    public function hasAllDocuments(): bool
    {
        return $this->hasCinCommercant() && $this->hasRegistreCommerce();
    }
}