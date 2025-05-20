<?php

// app/Models/ProfileCentre.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileCentre extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'capacite',
        'services_offerts',
        'document_legal',
        'disponibilite',
        'id_annonce',
        'id_album_photo',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
