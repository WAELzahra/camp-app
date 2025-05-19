<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileFournisseur extends Model
{
    protected $fillable = ['profile_id', 'intervale_prix', 'product_category'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}