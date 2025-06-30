<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Photos;

class Album extends Model
{
    use HasFactory;

    protected $fillable = ['titre', 'description', 'cover_image'];

    public function photos()
{
    return $this->hasMany(Photos::class);
}
}
