<?php

// app/Models/Profile.php

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
    ];
    protected $casts = [
        'activities' => 'array',
    ];

    // In Profile model
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
        // target_id dans Feedbacks correspond à user_id dans Profile
        return $this->hasMany(\App\Models\Feedbacks::class, 'target_id', 'user_id');
    }

 public function album()
    {
        // Un profil a un album (relation one-to-one)
        return $this->hasOne(Album::class, 'profile_id'); 
        // ou selon ta structure, si la clé étrangère est différente, adapte ici
    }

}
