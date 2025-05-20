<?php

// app/Models/ProfileGroupe.php

namespace App\Models;

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
}
