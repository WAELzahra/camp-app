<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileCentre extends Model
{
    protected $fillable = [
        'profile_id', 'capacite', 'service_offrant', 'document_legal',
        'disponibilite', 'id_annonce', 'id_album_photo'
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}