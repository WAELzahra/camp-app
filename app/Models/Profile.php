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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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

}
