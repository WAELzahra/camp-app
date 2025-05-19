<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileGroupe extends Model
{
    protected $fillable = [
        'profile_id', 'nom_groupe', 'id_album_photo', 'id_participant',
        'id_annonce', 'cin_responsable'
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
