<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Camping_Zones;

class CampingCentre extends Model
{
    use HasFactory;

    protected $table = 'camping_centres';

    protected $fillable = [
        'nom',
        'type',              // centre / hors_centre
        'description',
        'adresse',
        'lat',
        'lng',
        'image',
        'status',            // public (true) ou privé (false)
        'user_id',
        'profile_centre_id'
    ];

    /**
     * Profil du centre (infos supplémentaires si centre inscrit)
     */
    public function profileCentre()
    {
        return $this->belongsTo(ProfileCentre::class, 'profile_centre_id');
    }

    /**
     * Zones liées au centre
     */
    public function zones()
    {
        return $this->hasMany(Camping_Zones::class, 'centre_id');
    }

    /**
     * Utilisateur lié (si centre inscrit)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Vérifie si le centre est inscrit sur la plateforme
     */
    public function isRegistered()
    {
        return !is_null($this->user_id);
    }

    /**
     * Vérifie si le centre est public
     */
    public function isPublic()
    {
        return (bool) $this->status;
    }


    public function fullDetails()
{
    return [
        'id' => $this->id,
        'nom' => $this->nom,
        'type' => $this->type,
        'description' => $this->description,
        'adresse' => $this->adresse,
        'lat' => $this->lat,
        'lng' => $this->lng,
        'image' => $this->image,
        'status' => $this->isPublic(),
        'user' => $this->user ? [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'profile' => $this->user->profile,
            'profileCentre' => $this->user->profileCentre,
        ] : null,
        'zones' => $this->zones ? $this->zones->map(fn($zone) => [
            'id' => $zone->id,
            'nom' => $zone->nom,
            'lat' => $zone->lat,
            'lng' => $zone->lng,
            'description' => $zone->description,
        ]) : [],
    ];
}

}
