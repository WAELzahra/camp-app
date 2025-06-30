<?php

// app/Models/ProfileGroupe.php

namespace App\Models;
use App\Models\Profile;
use App\Models\Album;
use App\Models\User;
use App\Models\Photos; 

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
    ];

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


}
