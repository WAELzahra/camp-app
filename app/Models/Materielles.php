<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materielles extends Model
{
    use HasFactory;

    protected $fillable = [
        'fournisseur_id',
        'category_id',
        'nom',
        'description',
        // Pricing
        'tarif_nuit',       // rental price per night (nullable if not rentable)
        'prix_vente',       // sale price (nullable if not sellable)
        // Listing type
        'is_rentable',
        'is_sellable',
        // Stock
        'quantite_total',
        'quantite_dispo',
        // Delivery
        'livraison_disponible',
        'frais_livraison',
        // Visibility
        'status',
    ];

    protected $casts = [
        'is_rentable'          => 'boolean',
        'is_sellable'          => 'boolean',
        'livraison_disponible' => 'boolean',
        'tarif_nuit'           => 'float',
        'prix_vente'           => 'float',
        'frais_livraison'      => 'float',
    ];

    /**
     * The category this materiel belongs to.
     * Fixed: was hasOne (wrong direction) — should be belongsTo.
     */
    public function category()
    {
        return $this->belongsTo(Materielles_categories::class, 'category_id');
    }

    /**
     * The supplier who listed this materiel.
     * Fixed: was belongsTo(Feedbacks::class) — wrong model entirely.
     */
    public function fournisseur()
    {
        return $this->belongsTo(User::class, 'fournisseur_id');
    }

    /**
     * Photos attached to this materiel.
     */
    public function photos()
    {
        return $this->hasMany(Photo::class, 'materielle_id');
    }

    /**
     * Feedbacks/reviews left for this materiel.
     */
    public function feedbacks()
    {
        return $this->hasMany(Feedbacks::class, 'materielle_id');
    }

    /**
     * All reservations made for this materiel.
     */
    public function reservations()
    {
        return $this->hasMany(Reservations_materielles::class, 'materielle_id');
    }

    /**
     * Scope: only items available for rental.
     */
    public function scopeRentable($query)
    {
        return $query->where('is_rentable', true);
    }

    /**
     * Scope: only items available for sale.
     */
    public function scopeSellable($query)
    {
        return $query->where('is_sellable', true);
    }

    /**
     * Scope: only visible/active listings.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'up');
    }
}