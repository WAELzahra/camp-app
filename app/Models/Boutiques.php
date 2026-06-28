<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boutiques extends Model
{
    use HasFactory, HasSlug;

    protected string $slugSource = 'nom_boutique';

    protected $fillable = [
        'fournisseur_id',
        'nom_boutique',
        'slug',
        'description',
        'status',
        'path_to_img',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * The supplier who owns this boutique.
     */
    public function fournisseur()
    {
        return $this->belongsTo(User::class, 'fournisseur_id');
    }

    /**
     * All materiels listed in this boutique.
     */
    public function materielles()
    {
        return $this->hasMany(Materielles::class, 'fournisseur_id', 'fournisseur_id');
    }
}