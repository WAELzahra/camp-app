<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileFournisseur extends Model
{
    use HasFactory;

    protected $table = 'profile_fournisseurs';

    protected $fillable = [
        'profile_id',
        'intervale_prix',
        'product_category',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}