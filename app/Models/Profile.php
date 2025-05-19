<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



class Profile extends Model
{
    protected $fillable = [
        'user_id', 'date_naissance', 'sexe', 'bio', 'cover_image',
        'feedback', 'immatricule'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guide()
    {
        return $this->hasOne(ProfileGuide::class);
    }

    public function centre()
    {
        return $this->hasOne(ProfileCentre::class);
    }

    public function groupe()
    {
        return $this->hasOne(ProfileGroupe::class);
    }

    public function fournisseur()
    {
        return $this->hasOne(ProfileFournisseur::class);
    }
}

