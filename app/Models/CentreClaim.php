<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentreClaim extends Model
{
    use HasFactory;

    protected $table = 'centre_claims';

    protected $fillable = [
        'centre_id',
        'user_id',
        'status',
        'message',
        'proof_document',
        'admin_note',
        'reviewer_id',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function centre()
    {
        return $this->belongsTo(CampingCentre::class, 'centre_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
