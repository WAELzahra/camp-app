<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        "guide_id",
        "provider_id",
        "creation_date",
        "titre",
        "decription",
        "type",
        "icon",

    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}

